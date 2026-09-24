# Instalação do PService na VPS Ubuntu

## Opção mais simples
1. Envie `pservice-completo.zip` para a VPS.
2. Extraia o ZIP.
3. Execute:

```bash
sudo bash deploy/install_ubuntu.sh
```

O instalador pede a senha do administrador.

## Com domínio
```bash
sudo DOMAIN=pservice.seudominio.com.br bash deploy/install_ubuntu.sh
```

Depois que o DNS A/AAAA apontar para a VPS:

```bash
sudo apt-get install -y certbot python3-certbot-nginx
sudo certbot --nginx -d pservice.seudominio.com.br --redirect
```

## Instalar direto do ZIP
```bash
sudo bash deploy/install_from_zip.sh /root/pservice-completo.zip
```

## Primeiro acesso
O e-mail do administrador é `admin@pservice.local` se você não informar outro. A senha é a definida durante a instalação.

## Backup
O sistema cria backup diário às 02:30 em `storage/backups` e mantém o período configurado em `BACKUP_RETENTION_DAYS`.

## SMTP
A recuperação de senha usa o mailer configurado no `.env`. Para produção, troque `MAIL_MAILER=log` por SMTP e configure host, porta, usuário, senha e endereço remetente.
