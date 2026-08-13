#!/bin/sh
set -e

# Migration automatique au démarrage — nécessaire car le plan Render actuel
# n'a pas accès au Shell interactif pour lancer `artisan migrate` à la main.
# Ne bloque pas le démarrage du service en cas d'échec (ex. coupure réseau
# transitoire vers Neon) : le service précédent tournait déjà, mieux vaut le
# garder up et voir l'erreur dans les logs que de le laisser down.
php artisan migrate --force || echo "⚠️  Migration échouée — voir logs ci-dessus. Démarrage du service quand même."

exec php artisan serve --host=0.0.0.0 --port=8080
