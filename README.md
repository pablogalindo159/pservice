# PService

Registro fotográfico de Ordens de Serviço, por etapas (Entrada → Desmontagem → Bobinagem → Montagem → Testes → Finalização). Aplicação web responsiva + PWA, pensada para o técnico fotografar pelo celular e o gestor acompanhar pelo computador.

**Stack:** Laravel 13 · PHP 8.3 · SQLite (ou MySQL) · componente Image nativo (Intervention v4) · Nginx + PHP-FPM

## Funcionalidades

- Login com perfis: Administrador, Gerente, Laboratório, Técnico, Visualizador
- OS com número, cliente e status (Aberta, Em andamento, Aguardando, Finalizada, Cancelada)
- Envio de fotos pela câmera ou galeria, **uma por requisição**, com barra de progresso e nova tentativa automática em caso de falha de rede
- Original preservado + preview (1600 px) + miniatura (480 px)
- Nome padronizado: `OS1020_ENTRADA_2026-09-24_08-31-15_01.jpg`
- Download por etapa ou da OS inteira em ZIP (originais)
- Auditoria: login/logout, OS criada, status, foto adicionada/excluída, downloads, alterações de usuários, com IP
- Exclusão de foto é *soft delete*: o original continua em disco e nos backups
- Fotos fora da pasta pública, servidas só para usuários autenticados
- PWA instalável (Android/iPhone)
- Backup diário: snapshot consistente do banco + cópia externa via rclone

## Instalação em produção

Veja **[DEPLOY_VPS.md](DEPLOY_VPS.md)**.

## Desenvolvimento local

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
ADMIN_PASSWORD='SuaSenhaForte' php artisan migrate --seed
php artisan serve
```

Acesse `http://localhost:8000/login` com `admin@pservice.local`. Sem `ADMIN_PASSWORD`, o seeder gera uma senha aleatória e mostra no terminal.

Testes: `php artisan test`

## Comandos úteis

| Comando | O que faz |
|---|---|
| `php artisan pservice:backup` | Snapshot do banco + cópia externa (se configurada) |
| `php artisan pservice:thumbnails` | Gera preview/miniatura para fotos antigas (`--force` refaz todas) |

## Estrutura de arquivos das fotos

```
storage/app/private/
├── photos/{OS}/{ETAPA}/     originais
├── previews/{OS}/{ETAPA}/   1600 px (visualização)
└── thumbs/{OS}/{ETAPA}/     480 px (grade)
```

Etapas, status e perfis ficam em `config/pservice.php`.
