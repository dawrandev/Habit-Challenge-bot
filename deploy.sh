#!/usr/bin/env bash
# Repo ildizidan deploy: bash deploy.sh
#
# Asl skript — laravel/deploy.sh. Bu fayl faqat uni chaqiradi, shuning uchun
# deploy ildizdan ham, laravel/ ichidan ham bir xil ishlaydi.
set -e

exec bash "$(dirname "$0")/laravel/deploy.sh" "$@"
