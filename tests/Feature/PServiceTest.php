<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Photo;
use App\Models\ServiceOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_e_dashboard(): void
    {
        $user = User::factory()->create(['email' => 'a@a.com']);

        $this->post('/login', ['email' => 'a@a.com', 'password' => 'password123'])->assertRedirect('/dashboard');
        $this->get('/dashboard')->assertOk()->assertSee('Visão geral');
        $this->assertDatabaseHas('audit_logs', ['user_id' => $user->id, 'action' => 'auth.login']);
    }

    public function test_usuario_inativo_nao_entra_e_e_derrubado(): void
    {
        $user = User::factory()->create(['email' => 'b@b.com', 'active' => false]);
        $this->post('/login', ['email' => 'b@b.com', 'password' => 'password123'])->assertSessionHasErrors('email');

        $this->actingAs($user)->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_permissoes_por_perfil(): void
    {
        $esperado = [
            //             criar OS, fotografar, excluir, baixar, auditoria
            'admin' => [true, true, true, true, true],
            'manager' => [true, true, true, true, true],
            'laboratory' => [true, true, true, true, false],
            'technician' => [true, true, false, false, false],
            'viewer' => [false, false, false, false, false],
        ];
        foreach ($esperado as $role => $p) {
            $u = User::factory()->role($role)->make();
            $this->assertSame($p, [$u->canCreateOs(), $u->canTakePhotos(), $u->canDeletePhotos(), $u->canDownload(), $u->canAudit()], $role);
        }
        $this->assertSame('Laboratório', User::factory()->role('laboratory')->make()->role_label);
    }

    public function test_tecnico_cria_os_visualizador_nao(): void
    {
        $this->actingAs(User::factory()->role('viewer')->create())
            ->post('/os', ['number' => '1', 'client_name' => 'X'])->assertForbidden();

        $this->actingAs(User::factory()->role('technician')->create())
            ->post('/os', ['number' => 'OS1020', 'client_name' => 'Cliente'])->assertRedirect();

        $this->assertDatabaseHas('service_orders', ['number' => '1020']);
    }

    public function test_visualizador_nao_baixa(): void
    {
        $os = ServiceOrder::create(['number' => '9', 'client_name' => 'C']);
        $this->actingAs(User::factory()->role('viewer')->create())
            ->get("/os/{$os->number}/photos/download-all")->assertForbidden();
    }

    public function test_numero_da_os_rejeita_caminho(): void
    {
        $this->actingAs(User::factory()->role('admin')->create())
            ->post('/os', ['number' => '../../x', 'client_name' => 'X'])->assertSessionHasErrors('number');
    }

    public function test_upload_gera_original_preview_miniatura_e_auditoria(): void
    {
        Storage::fake('local');
        $user = User::factory()->role('technician')->create();
        $os = ServiceOrder::create(['number' => '1020', 'client_name' => 'C']);

        $this->actingAs($user)->postJson("/os/{$os->number}/photos", [
            'stage' => 'Entrada',
            'photos' => [UploadedFile::fake()->image('a.jpg', 2400, 1800)],
        ])->assertOk()->assertJson(['ok' => true]);

        $photo = Photo::firstOrFail();
        $this->assertMatchesRegularExpression('#^photos/1020/ENTRADA/OS1020_ENTRADA_\d{4}-\d\d-\d\d_\d\d-\d\d-\d\d_01\.jpg$#', $photo->original_path);
        Storage::disk('local')->assertExists([$photo->original_path, $photo->preview_path, $photo->thumbnail_path]);
        $this->assertSame('em_andamento', $os->fresh()->status);
        $this->assertTrue(AuditLog::where('action', 'photo.added')->exists());
    }

    public function test_visualizador_nao_envia_foto_e_etapa_invalida_e_rejeitada(): void
    {
        $os = ServiceOrder::create(['number' => '5', 'client_name' => 'C']);
        $file = UploadedFile::fake()->image('a.jpg');

        $this->actingAs(User::factory()->role('viewer')->create())
            ->post("/os/{$os->number}/photos", ['stage' => 'Entrada', 'photos' => [$file]])->assertForbidden();

        $this->actingAs(User::factory()->role('technician')->create())
            ->postJson("/os/{$os->number}/photos", ['stage' => 'Pintura', 'photos' => [$file]])->assertStatus(422);
    }

    public function test_exclusao_mantem_original_e_registra(): void
    {
        Storage::fake('local');
        $manager = User::factory()->role('manager')->create();
        $os = ServiceOrder::create(['number' => '7', 'client_name' => 'C']);
        $this->actingAs($manager)->postJson("/os/{$os->number}/photos", ['stage' => 'Testes', 'photos' => [UploadedFile::fake()->image('t.jpg')]]);
        $photo = Photo::firstOrFail();

        $this->delete("/photos/{$photo->id}")->assertRedirect();

        $this->assertSoftDeleted($photo);
        Storage::disk('local')->assertExists($photo->original_path);
        $this->assertTrue(AuditLog::where('action', 'photo.deleted')->where('photo_id', $photo->id)->exists());
    }

    public function test_url_da_os_usa_o_numero_e_link_antigo_redireciona(): void
    {
        $this->actingAs(User::factory()->create());
        $os = ServiceOrder::create(['number' => '1020', 'client_name' => 'C']);

        $this->get('/os/1020')->assertOk()->assertSee('id="OS1020-entrada"', false);
        $this->get("/os/{$os->id}")->assertRedirect('/os/1020');
        $this->get('/os/OS1020')->assertRedirect('/os/1020');
        $this->get('/os/9999')->assertNotFound();
    }

    public function test_busca_de_usuarios(): void
    {
        $admin = User::factory()->role('admin')->create(['name' => 'Pablo Admin']);
        User::factory()->role('technician')->create(['name' => 'João Técnico', 'email' => 'joao@oficina.com']);
        User::factory()->role('laboratory')->create(['name' => 'Maria Lab', 'active' => false]);

        $this->actingAs($admin)->get('/usuarios?q=joao')->assertOk()->assertSee('João Técnico')->assertDontSee('Maria Lab');
        $this->get('/usuarios?q=oficina.com')->assertSee('João Técnico');
        $this->get('/usuarios?role=laboratory')->assertSee('Maria Lab')->assertDontSee('João Técnico');
        $this->get('/usuarios?status=inativo')->assertSee('Maria Lab')->assertDontSee('João Técnico');
        $this->get('/usuarios?q=ninguem')->assertSee('Nenhum usuário encontrado');
    }

    private function fotoEm(ServiceOrder $os, string $stage): Photo
    {
        $user = User::first() ?? User::factory()->create();
        $p = Photo::create([
            'service_order_id' => $os->id, 'user_id' => $user->id, 'stage' => $stage,
            'original_path' => "photos/{$os->number}/x_01.jpg", 'thumbnail_path' => 't.jpg',
            'mime_type' => 'image/jpeg', 'captured_at' => now(),
        ]);
        AuditLog::record('photo.added', $os->id, $p->id, ['stage' => $stage]);

        return $p;
    }

    public function test_finalizacao_automatica_apos_24h_sem_alteracoes(): void
    {
        $this->travelTo(now()->subHours(25));
        $parada = ServiceOrder::create(['number' => '100', 'client_name' => 'A', 'status' => 'em_andamento']);
        $this->fotoEm($parada, 'Finalização');
        $aguardando = ServiceOrder::create(['number' => '101', 'client_name' => 'B', 'status' => 'aguardando']);
        $this->fotoEm($aguardando, 'Finalização');
        $mexida = ServiceOrder::create(['number' => '102', 'client_name' => 'C', 'status' => 'em_andamento']);
        $this->fotoEm($mexida, 'Finalização');
        $semFinal = ServiceOrder::create(['number' => '103', 'client_name' => 'D', 'status' => 'em_andamento']);
        $this->fotoEm($semFinal, 'Testes');
        $this->travelBack();

        $this->travelTo(now()->subHours(2));
        $this->fotoEm($mexida, 'Testes');          // alguém mexeu há 2h
        $this->travelBack();

        $this->artisan('pservice:auto-finalizar')->assertSuccessful();

        $this->assertSame('finalizada', $parada->fresh()->status);
        $this->assertSame('aguardando', $aguardando->fresh()->status);
        $this->assertSame('em_andamento', $mexida->fresh()->status);
        $this->assertSame('em_andamento', $semFinal->fresh()->status);

        $log = AuditLog::where('service_order_id', $parada->id)->where('action', 'os.status_changed')->firstOrFail();
        $this->assertNull($log->user_id);
        $this->assertTrue($log->metadata['auto']);
        $this->assertSame('Status alterado (automático)', $log->action_label);
    }

    public function test_simular_nao_altera(): void
    {
        $this->travelTo(now()->subHours(30));
        $os = ServiceOrder::create(['number' => '200', 'client_name' => 'A', 'status' => 'em_andamento']);
        $this->fotoEm($os, 'Finalização');
        $this->travelBack();

        $this->artisan('pservice:auto-finalizar --simular')->assertSuccessful();
        $this->assertSame('em_andamento', $os->fresh()->status);
    }

    public function test_primeira_foto_muda_status_e_registra_automatico(): void
    {
        Storage::fake('local');
        $os = ServiceOrder::create(['number' => '300', 'client_name' => 'C']);
        $this->actingAs(User::factory()->role('technician')->create())
            ->postJson("/os/{$os->number}/photos", ['stage' => 'Entrada', 'photos' => [UploadedFile::fake()->image('a.jpg')]])
            ->assertOk()->assertJson(['os_status' => 'em_andamento']);

        $this->assertTrue(AuditLog::where('service_order_id', $os->id)->where('action', 'os.status_changed')->where('metadata->auto', true)->exists());
    }

    public function test_foto_exige_login(): void
    {
        $user = User::factory()->create();
        $os = ServiceOrder::create(['number' => '8', 'client_name' => 'C']);
        $photo = Photo::create([
            'service_order_id' => $os->id, 'user_id' => $user->id, 'stage' => 'Entrada',
            'original_path' => 'x.jpg', 'thumbnail_path' => 'y.jpg', 'mime_type' => 'image/jpeg', 'captured_at' => now(),
        ]);

        $this->get("/photos/{$photo->id}/file")->assertRedirect('/login');
    }

    public function test_apenas_admin_gerencia_usuarios(): void
    {
        $this->actingAs(User::factory()->role('manager')->create())->get('/usuarios')->assertForbidden();

        $admin = User::factory()->role('admin')->create();
        $this->actingAs($admin)->get('/usuarios')->assertOk();
        $this->patch("/usuarios/{$admin->id}", ['name' => 'Eu', 'role' => 'viewer'])->assertSessionHasErrors('role');
    }
}
