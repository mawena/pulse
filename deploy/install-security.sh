#!/usr/bin/env bash
#
# MawenaPulse — installation de la configuration sécurité sur le VPS.
# À exécuter en root depuis la racine du projet :
#   sudo bash deploy/install-security.sh
#
# Installe :
#   - les wrappers /usr/local/bin/pulse-kill et pulse-service (root:root, 755)
#   - la règle sudoers /etc/sudoers.d/mawenapulse (root:root, 0440, validée)

set -euo pipefail

if [[ $EUID -ne 0 ]]; then
    echo "Ce script doit être exécuté en root (sudo)." >&2
    exit 1
fi

here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

echo "→ Installation des wrappers…"
install -o root -g root -m 755 "$here/bin/pulse-kill" /usr/local/bin/pulse-kill
install -o root -g root -m 755 "$here/bin/pulse-service" /usr/local/bin/pulse-service

echo "→ Installation de la règle sudoers…"
install -o root -g root -m 440 "$here/sudoers.d/mawenapulse" /etc/sudoers.d/mawenapulse

echo "→ Validation de la syntaxe sudoers…"
visudo -c -f /etc/sudoers.d/mawenapulse

echo "→ Test des wrappers (doivent refuser les entrées invalides)…"
! /usr/local/bin/pulse-kill HUP 1234 2>/dev/null || { echo "ÉCHEC: signal HUP accepté" >&2; exit 1; }
! /usr/local/bin/pulse-kill TERM "12; reboot" 2>/dev/null || { echo "ÉCHEC: PID injecté accepté" >&2; exit 1; }
! /usr/local/bin/pulse-service stop nginx 2>/dev/null || { echo "ÉCHEC: action stop acceptée" >&2; exit 1; }
! /usr/local/bin/pulse-service restart sshd 2>/dev/null || { echo "ÉCHEC: unité sshd acceptée" >&2; exit 1; }

echo "✅ Sécurité installée. Ajouter PULSE_USE_SUDO=true dans le .env de l'application."
