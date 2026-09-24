<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\AuditLog;
use App\Models\Photo;
use App\Models\Setting;
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

    private function areaAtiva(string $mode = 'photos'): void
    {
        Setting::put(['geo_enabled' => '1', 'geo_lat' => '-25.5347', 'geo_lng' => '-49.2064', 'geo_radius' => '150', 'geo_mode' => $mode]);
    }

    public function test_area_desativada_nao_restringe(): void
    {
        $this->actingAs(User::factory()->role('technician')->create())->get('/os')->assertOk();
    }

    public function test_tecnico_fora_da_area_entra_mas_fica_bloqueado(): void
    {
        $this->areaAtiva('block');
        $tec = User::factory()->role('technician')->create(['email' => 't@t.com']);

        // Login funciona e fica na auditoria
        $this->post('/login', ['email' => 't@t.com', 'password' => 'password123'])->assertRedirect();
        $this->assertDatabaseHas('audit_logs', ['user_id' => $tec->id, 'action' => 'auth.login']);

        // Sem localização confirmada: vai para a tela de verificação
        $this->get('/os')->assertRedirect('/fora-da-area');
        $this->get('/fora-da-area')->assertOk()->assertSee('Verificando sua localização');

        // Em Curitiba (~13 km): fora
        $this->postJson('/localizacao', ['lat' => -25.4284, 'lng' => -49.2733, 'acc' => 20])
            ->assertOk()->assertJson(['inside' => false]);
        $this->get('/os')->assertRedirect('/fora-da-area');
        $this->get('/dashboard')->assertRedirect('/fora-da-area');
        $os = ServiceOrder::create(['number' => '1', 'client_name' => 'C']);
        $this->postJson("/os/{$os->number}/photos", ['stage' => 'Entrada'])->assertStatus(403);
        $this->assertTrue(AuditLog::where('user_id', $tec->id)->where('action', 'geo.check')->exists());

        // Dentro da empresa (~100 m): liberado
        $this->postJson('/localizacao', ['lat' => -25.5338, 'lng' => -49.2064, 'acc' => 15])
            ->assertOk()->assertJson(['inside' => true]);
        $this->get('/os')->assertOk();

        // Localização negada: bloqueado
        $this->postJson('/localizacao', ['error' => 'negada'])->assertJson(['inside' => false]);
        $this->get('/os')->assertRedirect('/fora-da-area');
    }

    public function test_modo_fotos_fora_da_area_envia_mas_nao_ve(): void
    {
        Storage::fake('local');
        $this->areaAtiva('photos');
        $tec = User::factory()->role('technician')->create(['name' => 'João']);
        $this->actingAs($tec);
        $os = ServiceOrder::create(['number' => '221', 'client_name' => 'C']);
        $foto = $this->fotoEm($os, 'Entrada');

        // Fora da área (Curitiba)
        $this->postJson('/localizacao', ['lat' => -25.4284, 'lng' => -49.2733, 'acc' => 20])
            ->assertJson(['inside' => false, 'mode' => 'photos']);
        $this->assertSame(1, Alert::where('type', 'geo.outside')->where('user_id', $tec->id)->count());

        // Navega e vê a OS, mas sem as fotos
        $this->get('/os')->assertOk()->assertSee('Fora da área da empresa');
        $this->get('/os/221')->assertOk()->assertSee('photo locked', false)->assertDontSee('data-view=', false);
        $this->get("/photos/{$foto->id}/file?size=thumb")->assertForbidden();
        $this->get('/os/221/photos/download-all')->assertRedirect('/os');
        $this->patch('/os/221/status', ['status' => 'finalizada'])->assertRedirect('/os');
        $this->post('/os', ['number' => '9', 'client_name' => 'X'])->assertRedirect('/os');

        // Envia fotos normalmente: recebe só o "cadeado" e gera alerta agrupado
        for ($i = 0; $i < 2; $i++) {
            $this->postJson('/os/221/photos', ['stage' => 'Testes', 'photos' => [UploadedFile::fake()->image("t{$i}.jpg")]])
                ->assertOk()->assertJsonPath('photos.0.locked', true)->assertJsonMissingPath('photos.0.view');
        }
        $alert = Alert::where('type', 'photo.outside')->firstOrFail();
        $this->assertSame(2, $alert->meta['count']);
        $this->assertStringContainsString('enviou 2 foto(s) fora da área na OS221', $alert->message);

        // Voltou para a área: vê tudo
        $this->postJson('/localizacao', ['lat' => -25.5347, 'lng' => -49.2064, 'acc' => 10])->assertJson(['inside' => true]);
        $this->get('/os/221')->assertSee('data-view=', false);
        $this->get("/photos/{$foto->id}/file?size=thumb")->assertStatus(404); // passa pela trava (arquivo falso não existe)
    }

    public function test_alertas_so_para_admin_e_gerente(): void
    {
        Alert::create(['type' => 'geo.outside', 'message' => 'Teste fora da área']);
        $this->actingAs(User::factory()->role('technician')->create())->get('/alertas')->assertForbidden();

        $ger = User::factory()->role('manager')->create();
        $this->actingAs($ger)->get('/dashboard')->assertSee('1 alerta(s) novo(s)');
        $this->get('/alertas')->assertOk()->assertSee('Teste fora da área');
        $this->post('/alertas/vistos')->assertRedirect();
        $this->assertSame(0, Alert::unseenCount());
        $this->assertSame($ger->id, Alert::first()->seen_by);
    }

    public function test_admin_e_gerente_nao_sao_restritos(): void
    {
        $this->areaAtiva();
        $this->actingAs(User::factory()->role('admin')->create())->get('/os')->assertOk();
        $this->actingAs(User::factory()->role('manager')->create())->get('/os')->assertOk();
    }

    public function test_verificacao_expira(): void
    {
        $this->areaAtiva();
        $this->actingAs(User::factory()->role('laboratory')->create());
        $this->postJson('/localizacao', ['lat' => -25.5347, 'lng' => -49.2064, 'acc' => 10])->assertJson(['inside' => true]);
        $this->get('/os')->assertOk();
        $this->travel(11)->minutes();
        $this->get('/os')->assertRedirect('/fora-da-area');
    }

    public function test_so_admin_configura_area(): void
    {
        $this->actingAs(User::factory()->role('manager')->create())->get('/configuracoes')->assertForbidden();
        $this->actingAs(User::factory()->role('admin')->create())
            ->post('/configuracoes', ['enabled' => '1', 'lat' => '-25.5347', 'lng' => '-49.2064', 'radius' => 150, 'mode' => 'photos'])
            ->assertRedirect();
        $this->assertSame('1', Setting::get('geo_enabled'));
        $this->assertTrue(AuditLog::where('action', 'settings.geo')->exists());
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
