#!/bin/bash
# Запустить на сервере под root (уже открытый ssh → root):
#   bash -s < install-deploy-key.sh
# или скопировать блок ниже целиком.

set -e

# Ключи, которым разрешён деплой (Mac nagruzka + старый alashgym)
PUBS=(
  'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIIT1+kNCBWzallPMbb6YNkmAvCybLkOEZdaJjQGv4uDv nagruzka-deploy'
  'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIKVGiPoKry95QSsImkyaLvBzeFacDZEu33lb57+gPKxD alashgym-deploy'
)

install_key() {
  local home="$1" owner="$2"
  mkdir -p "$home/.ssh"
  touch "$home/.ssh/authorized_keys"
  local pub
  for pub in "${PUBS[@]}"; do
    grep -qxF "$pub" "$home/.ssh/authorized_keys" 2>/dev/null || echo "$pub" >> "$home/.ssh/authorized_keys"
  done
  chmod 700 "$home/.ssh"
  chmod 600 "$home/.ssh/authorized_keys"
  chown -R "$owner:$owner" "$home/.ssh"
  echo "OK key → $home"
}

install_key /root root
if id sh >/dev/null 2>&1; then
  install_key /home/sh sh
fi

# sh может sudo без пароля только для деплоя
if id sh >/dev/null 2>&1; then
  cat > /etc/sudoers.d/alashgym-deploy <<'EOF'
sh ALL=(ALL) NOPASSWD: /usr/bin/tar, /bin/tar, /bin/rm, /usr/bin/rm, /bin/chown, /usr/bin/chown, /bin/chmod, /usr/bin/chmod, /bin/bash, /usr/bin/bash
EOF
  chmod 440 /etc/sudoers.d/alashgym-deploy
  echo OK sudoers
fi

SITE=/var/www/sportdecor_k_usr/data/www/sportdecor.kz/alashgym
if [ -d "$SITE" ]; then
  chown -R sportdecor_k_usr:sh "$SITE" 2>/dev/null || chown -R sportdecor_k_usr:sportdecor_k_usr "$SITE"
  chmod -R g+rwX "$SITE"
  echo "OK perms → $SITE"
fi
echo DONE
