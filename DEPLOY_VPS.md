# Instalação do PService em VPS Ubuntu (22.04 / 24.04)

## 1. DNS

Crie um registro **A** apontando `pservice.seudominio.com.br` para o IP da VPS.

## 2. Instalação (via GitHub)

```bash
sudo apt-get update && sudo apt-get install -y git
sudo git clone https://github.com/pablogalindo159/pservice.git /var/www/pservice
cd /var/www/pservice
sudo DOMAIN=pservice.seudominio.com.br \
     CERTBOT_EMAIL=voce@seudominio.com.br \
     ADMIN_EMAIL=voce@seudominio.com.br \
     bash deploy/install_ubuntu.sh
```

O instalador pede a senha do administrador (ela **não** é gravada em nenhum arquivo). Com `CERTBOT_EMAIL`, o HTTPS é emitido na hora e o cookie de sessão passa a ser `secure`. Se o DNS ainda não propagou, rode o instalador de novo depois; ele é seguro para reexecução.

> **VPS com outros sistemas:** sempre informe `DOMAIN`. Sem ele o instalador se recusa a continuar se encontrar outros sites no Nginx, para não virar o site padrão da máquina.

Se o repositório for privado, use uma *deploy key* (chave SSH só de leitura cadastrada no repositório) e clone com `git@github.com:pablogalindo159/pservice.git`. Não coloque tokens dentro do código.

## 3. Atualizações

```bash
cd /var/www/pservice && sudo bash deploy/update.sh
```

Faz snapshot do banco, coloca o sistema em manutenção, `git pull`, `composer install`, migrações e volta ao ar. `.env`, banco e fotos não são tocados.

## 4. Backup fora da VPS (importante)

O backup diário (02:30) faz um snapshot consistente do banco em `storage/backups` (retenção em `BACKUP_RETENTION_DAYS`). **As fotos só saem da VPS se você configurar o rclone:**

```bash
sudo apt-get install -y rclone
sudo -u www-data rclone config          # crie um remoto, ex: "b2" (Backblaze B2), S3, Google Drive...
sudo nano /var/www/pservice/.env        # BACKUP_RCLONE_REMOTE=b2:pservice-backup
cd /var/www/pservice && sudo -u www-data php artisan config:cache
sudo -u www-data php artisan pservice:backup   # teste
```

O envio usa `rclone copy` (incremental, nunca apaga nada no destino), então só as fotos novas trafegam a cada noite.

## 5. E-mail (recuperação de senha)

No `.env`, troque `MAIL_MAILER=log` por `smtp` e preencha `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD` e `MAIL_FROM_ADDRESS`. Depois: `sudo -u www-data php artisan config:cache`.

Enquanto isso, o administrador pode redefinir a senha de qualquer usuário na tela **Usuários**.

## 6. Migrando da versão 1 (ZIP original)

Se já instalou a versão anterior em `/var/www/pservice`:

```bash
cd /var/www/pservice
sudo git init -q && sudo git remote add origin https://github.com/pablogalindo159/pservice.git
sudo git fetch -q origin && sudo git reset --hard origin/main
sudo DOMAIN=pservice.seudominio.com.br bash deploy/install_ubuntu.sh   # preserva .env, banco e fotos
sudo -u www-data php artisan pservice:thumbnails                       # gera previews das fotos antigas
```

O instalador também remove a senha do administrador que a versão 1 deixava gravada no `.env`.

## Arquivos e permissões

| Caminho | Dono | Observação |
|---|---|---|
| código (`app`, `config`, `public`…) | root:www-data, 640/750 | o PHP não consegue alterar o código |
| `storage/`, `bootstrap/cache/`, `database/` | www-data | dados graváveis |
| `.env` | root:www-data, 640 | |
| `/etc/cron.d/pservice` | | `schedule:run` a cada minuto |
