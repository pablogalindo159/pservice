# PService — Fase 8 — Instalação Laravel

Esta fase transforma o protótipo em uma base Laravel executável. O pacote NÃO inclui `vendor/`; o Composer instala as dependências no servidor.

## Requisitos
- PHP 8.3+
- Composer
- SQLite para teste ou MySQL para produção
- Extensões PHP: PDO, Fileinfo, GD e Zip
- HTTPS recomendado em produção

## Instalação local
1. Extraia o ZIP.
2. Execute `composer install`.
3. Copie `.env.example` para `.env`.
4. Execute `php artisan key:generate`.
5. Para teste rápido com SQLite, mantenha:
   `DB_CONNECTION=sqlite`
   `DB_DATABASE=database/database.sqlite`
6. Execute `php artisan migrate --seed`.
7. Execute `php artisan storage:link` se usar o disco público em outras partes do projeto.
8. Execute `php artisan serve`.
9. Acesse `/login`.

## Primeiro acesso
E-mail: `admin@pservice.local`
Senha: `TroqueEstaSenha123!`

Troque a senha imediatamente antes de uso real.

## Produção
- Configure MySQL no `.env`.
- Use HTTPS.
- Configure backup do banco.
- Configure armazenamento de fotos em infraestrutura de produção.
- Não mantenha `APP_DEBUG=true`.
- Configure `APP_URL` para o domínio real.
- Execute `php artisan optimize`.

## Fluxo atual
Login → Dashboard → OS → etapa → câmera/galeria → original + thumbnail → auditoria → download ZIP.

## Próxima etapa
Conectar o repositório GitHub e fazer a publicação/deploy real no servidor, sem colocar tokens de acesso dentro do código ou do chat.
