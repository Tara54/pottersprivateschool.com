# Potters School - site web et espace de gestion

Réalisé par Cembè Conseil & Services - contact@cembe.net - cembe.net

## Contenu

| Élément | Rôle |
|---|---|
| `index.php` | Site public bilingue. Français à `/`, anglais à `/?lang=en` (les deux versions sont indexables par Google) |
| `admin/` | Espace de gestion de l'école (connexion obligatoire) |
| `api/preinscription.php` | Reçoit les pré-inscriptions envoyées depuis le site |
| `inc/bootstrap.php` | Base de données, sécurité, langues, réglages |
| `sitemap.php`, `robots.php` | Plan du site et consignes pour Google (servis à `/sitemap.xml` et `/robots.txt`) |
| `site.webmanifest` | Icône et couleurs quand le site est ajouté à l'écran d'accueil d'un téléphone |
| `assets/` | Logo, photos (JPG + WebP), styles, scripts |
| `uploads/` | Photos ajoutées depuis l'espace de gestion |
| `data/` | Base SQLite `potters.sqlite` et journal d'erreurs, créés automatiquement |

Aucun framework, aucune dépendance à installer (pas de Composer ni de Node).

## Prérequis

- PHP 8.1 ou plus récent, avec `pdo_sqlite`, `gd` (avec WebP) et `zip` (pour la sauvegarde).
- Apache recommandé (les fichiers `.htaccess` sont fournis, `mod_rewrite` et `mod_headers` conseillés). Avec Nginx, voir plus bas.
- Un certificat HTTPS. L'espace de gestion ne doit pas être utilisé sans HTTPS.

## Installation

1. Copier tout le dossier à la racine du site (souvent `public_html/` ou `www/`).
2. Donner à PHP le droit d'écrire dans `data/` et `uploads/` (755 ou 775 selon l'hébergeur).
3. Ouvrir `https://<domaine>/admin/` : la première visite demande de créer le compte de la direction.
4. Dans "Infos de l'école", renseigner **l'adresse du site** (ex. `https://www.potters-school.bf`), puis vérifier numéros, WhatsApp et e-mail.
5. Décommenter les 2 lignes "Forcer HTTPS" du `.htaccess` racine une fois le certificat actif.
6. Référencement : déclarer le site dans Google Search Console et y soumettre `https://<domaine>/sitemap.xml`. Créer la fiche Google Business Profile de l'école (adresse, horaires, photos) : c'est elle qui fait apparaître l'école sur Google Maps.

Pour tester sur un poste : `php -S 127.0.0.1:8000` dans le dossier, puis ouvrir http://127.0.0.1:8000.

## Nginx

```nginx
location ~ ^/(data|inc)/ { deny all; }
location ~ ^/uploads/.*\.(php|phtml|phar)$ { deny all; }
location ~ /\. { deny all; }
location ~ \.(sqlite|md|log|zip)$ { deny all; }
location = /sitemap.xml { rewrite ^ /sitemap.php last; }
location = /robots.txt { rewrite ^ /robots.php last; }
```

## Le site public

- Version française et version anglaise générées par le serveur (balises `hreflang`, URL canonique). Le bouton FR/EN bascule aussi sans recharger la page.
- Style inspiré d'Apple : grands titres, beaucoup d'espace, section "Une journée, deux langues" racontée au défilement (la section reste fixe, le matin, l'après-midi puis les deux certificats se succèdent, le fond change de couleur et le soleil avance). Sur téléphone, les trois moments s'affichent à la suite.
- Effets inspirés de React Bits, écrits sans React : titre qui apparaît lettre par lettre dans un flou, mots qui tournent ("Ici, votre enfant apprend à lire, compter..."), cartes qui s'inclinent sous la souris avec un reflet, bouton principal aimanté, fond aurore animé derrière la pré-inscription.
- Menu mobile, en-tête qui se réduit au défilement, barre de progression de lecture, lien actif selon la section.
- Animations d'arrivée sur l'accueil, apparition des sections au défilement (navigateurs récents, sans script), soleil qui traverse la "journée en deux langues", compteurs animés. Tout est désactivé si l'appareil demande moins d'animations.
- Liste des pièces à fournir à cocher, mémorisée sur le téléphone du parent.
- Galerie avec visionneuse plein écran (clavier, glissement du doigt).
- Questions fréquentes en accordéon.
- Pré-inscription en 3 étapes avec vérification avant envoi, puis envoi WhatsApp en un clic.
- Carte Google chargée seulement quand le visiteur la demande (plus rapide, moins de traceurs).

## Référencement (Google)

- Titre et description optimisés en français et en anglais, avec les mots "école bilingue", "Ouaga 2000", "Ouagadougou".
- Données structurées schema.org : `School` (nom, adresse, téléphones, logo, langues) et `FAQPage` (questions des parents).
- Open Graph : belle carte d'aperçu quand le lien est partagé sur WhatsApp ou Facebook.
- `sitemap.xml` avec les deux langues et l'image principale, `robots.txt` qui exclut l'espace de gestion.
- Images WebP (2 fois plus légères), dimensions déclarées, chargement différé, image principale préchargée.
- Une seule balise H1, titres hiérarchisés, textes alternatifs sur toutes les images.

## Fonctions de l'espace de gestion

- **Tableau de bord**, **Pré-inscriptions** (statut, notes, appel ou WhatsApp en un clic, export CSV), **Actualités** et **Galerie** (FR/EN, WebP automatique).
- **Infos de l'école** (direction) : adresse du site, nom, devise, téléphones, WhatsApp, e-mail, adresse FR/EN, Google Maps, année scolaire, bandeau d'inscriptions, Facebook.
- **Comptes** (direction) : comptes Direction ou Secrétariat, désactivation, nouveau mot de passe, historique (connexions comprises), **sauvegarde complète téléchargeable**.

## Sécurité

**Échanges avec le navigateur**
- En-têtes : Content-Security-Policy stricte (aucun script en ligne autorisé), HSTS en HTTPS, X-Frame-Options, Referrer-Policy, Permissions-Policy, X-Content-Type-Options, Cross-Origin-Opener-Policy.
- Version de PHP masquée, erreurs jamais affichées au public (enregistrées dans `data/errors.log`).

**Comptes**
- Mots de passe chiffrés (`password_hash`, mise à jour automatique de l'algorithme), 10 caractères minimum avec lettres et chiffres, mots de passe courants refusés.
- Blocage 15 minutes après 5 échecs sur un même compte ou 10 depuis une même adresse IP. Délai aléatoire après un échec. Vérification en temps constant.
- Session : cookie `HttpOnly`, `SameSite=Lax`, `Secure` et préfixe `__Host-` en HTTPS, identifiant renouvelé à la connexion, expiration après 30 minutes d'inactivité et 8 heures au maximum, liée au navigateur.
- Deux niveaux de droits (Direction, Secrétariat), historique des actions et des connexions.

**Formulaires**
- Jeton anti-falsification (CSRF) sur tous les formulaires de gestion.
- Formulaire public : champ piège, jeton horodaté signé (refus si envoyé en moins de 3 secondes ou après 6 heures), vérification de l'origine, 5 envois par heure par appareil et 200 par jour au total.
- Requêtes préparées partout, échappement de tout texte affiché, export CSV protégé contre l'injection de formules.

**Fichiers**
- Photos vérifiées, limitées en poids et en taille, puis ré-encodées : un faux fichier image est refusé.
- Aucun script ne peut s'exécuter dans `uploads/`. Dossiers `data/` et `inc/` inaccessibles depuis le web. Fichiers cachés, bases, journaux et sauvegardes refusés.

## Sauvegarde

Dans **Comptes**, la direction télécharge une sauvegarde complète (base + photos). À faire chaque semaine. Pour restaurer : remettre `data/potters.sqlite` et `uploads/` à leur place.

## Données personnelles

Les pré-inscriptions contiennent des données sur des enfants et leurs parents. Seuls les comptes autorisés y accèdent. Supprimer les demandes "Sans suite" en fin d'année scolaire. Les formalités auprès de la CIL (Commission de l'Informatique et des Libertés) restent à la charge de l'établissement. Les photos d'élèves ne doivent être publiées qu'avec l'accord des parents.

## Support

Cembè Conseil & Services - contact@cembe.net
