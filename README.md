<p align="center">
  <img src="public/logo.png" alt="GLPI BrandPulse — TiniSys IT Solutions" width="220">
</p>

<h1 align="center">GLPI BrandPulse</h1>

<p align="center">
  <img src="https://img.shields.io/badge/version-0.2.12-blue" alt="Version 0.2.12">
  <img src="https://img.shields.io/badge/GLPI-11.x-blue" alt="GLPI 11">
  <img src="https://img.shields.io/badge/PHP-8.2%2B-777bb4" alt="PHP 8.2+">
  <img src="https://img.shields.io/badge/license-GPL--3.0--or--later-green" alt="License GPL-3.0-or-later">
  <img src="https://img.shields.io/badge/status-active-success" alt="Status: active">
</p>

**GLPI BrandPulse** personnalise l'identité visuelle de GLPI et ajoute des compteurs rapides dans le header. Le plugin est pensé pour les équipes qui veulent une interface GLPI plus lisible, plus cohérente avec leur marque, et plus efficace au quotidien.

> English version below.

## Français

### Aperçu

BrandPulse regroupe cinq espaces de configuration :

- **Brand** : personnalisation des logos, du titre navigateur, du favicon, du fond de login et de l'identité visuelle.
- **Login alert** : message d'information visible sur la page de connexion, avec icône, mise en forme simple et affichage extensible.
- **Pulse** : compteurs dans le header GLPI pour suivre rapidement les tickets, les tâches ou des recherches sauvegardées.
- **Fonctionnalités** : options complémentaires, dont l’accès direct aux interfaces web des équipements par leur adresse IP.
- **Diagnostic** : informations utiles pour vérifier la version installée, l'état des options et les fichiers d'images importés.

### Fonctionnalités Brand

- Changer le titre affiché dans l'onglet du navigateur.
- Configurer un favicon personnalisé.
- Définir des logos différents pour le menu latéral GLPI.
- Prévoir des variantes pour les thèmes clair, sombre et neutre.
- Personnaliser le logo de la page de connexion.
- Ajouter une image de fond sur la page de login.
- Conserver un rendu propre lorsque le plugin est désactivé ou en attente de mise à jour.

### Fonctionnalités Login alert

- Afficher un message sur la page de connexion.
- Choisir le type d'alerte : info, warning, danger ou success.
- Choisir une icône depuis le pack intégré.
- Rédiger un message court ou plus long.
- Ouvrir automatiquement les longs messages si besoin.
- Utiliser une mini barre de mise en forme : titre, gras, liste, code et lien.

### Fonctionnalités Pulse

- Ajouter des compteurs visibles dans le header GLPI.
- Choisir le libellé, l'icône et la couleur de chaque compteur.
- Activer ou désactiver chaque compteur.
- Réordonner les compteurs.
- Utiliser des presets simples fournis par le plugin.
- Utiliser des recherches sauvegardées GLPI pour créer des compteurs adaptés à votre organisation.
- Définir des seuils warning et critical.
- Régler l'intervalle de rafraîchissement.
- Recalculer les compteurs à l'ouverture d'une page, après une sauvegarde AJAX
  GLPI et au retour sur l'onglet ou dans l'historique, sans F5.
- Réduire la recherche globale GLPI à une icône loupe.
- Autoriser l'affichage de Pulse profil par profil depuis l'onglet BrandPulse des profils GLPI.
- Ouvrir directement l'interface web d'un équipement depuis sa fiche principale,
  à partir de l'adresse IP renseignée dans ses ports réseau (HTTPS ou HTTP).

Pour personnaliser un tableau de tickets, cliquez sur le bouton **Colonnes à afficher**
de la ligne du compteur dans l’onglet **Pulse**. Désactivez **Utiliser les préférences
GLPI**, ajoutez les colonnes, réordonnez-les par glisser-déposer natif GLPI ou avec les flèches et choisissez le champ
et le sens du tri. Cliquez sur **Appliquer**, puis enregistrez la configuration Pulse.
Les colonnes fixes de GLPI restent en tête. Ces réglages appartiennent au compteur
du catalogue et s’appliquent aux profils qui l’utilisent, sans modifier les
préférences personnelles GLPI. Les filtres du compteur restent conservés.

Le panneau **Composition du bandeau** présente les compteurs affichés et ceux
qui restent disponibles. Ajoutez ou retirez un compteur, puis déplacez sa poignée
ou utilisez les flèches : sa nouvelle position est mise en évidence dans le
panneau et le catalogue. L’aperçu du bandeau suit les noms, couleurs, icônes et
l’ordre ; ses valeurs sont des exemples. En mode par profil, sélectionnez le
profil dans ce panneau. Le changement de profil protège les modifications non
enregistrées. Enregistrez la configuration Pulse pour appliquer les changements.

En mode **Pulse par profil**, la zone **Autoriser spécifiquement des profils**
s’affiche dans **Réglages par défaut** sous le choix du mode. Le champ multisélection
natif GLPI présente les profils sous forme d’étiquettes. Enregistrez la configuration
Pulse pour appliquer cette sélection au droit natif **Afficher Pulse général**.
La modification exige le droit GLPI de modifier les profils ; les profils en lecture
seule et leurs autorisations sont conservés.

La liste **Profil GLPI** de la composition affiche uniquement les profils autorisés.
Elle suit immédiatement la sélection ci-dessus. Si vous retirez le profil ouvert,
enregistrez la sélection avant d’ouvrir une autre composition ; les modifications
en cours restent attachées à leur profil. Une sélection vide est acceptée.

En mode global, le panneau repliable **Profils autorisés à afficher Pulse** conserve
son enregistrement indépendant des compositions. Les autorisations Pulse ne donnent
aucun droit supplémentaire sur les tickets ; les restrictions du libre-service
restent applicables.

Le bandeau, le catalogue et les colonnes utilisent le composant de glisser-déposer
fourni par GLPI, comme les règles, avec une zone de dépôt visible. Les flèches
restent disponibles au clavier. Les colonnes fixes suivent l’ordre imposé par GLPI.

### Visibilité de Pulse selon le profil

L'affichage de Pulse général peut être autorisé séparément pour chaque profil depuis :

```text
Administration > Profils > [profil] > BrandPulse
```

Le droit **Afficher Pulse général** est activé par défaut pour les profils utilisant l'interface standard et désactivé pour les profils self-service. Un administrateur peut adapter ce choix à chaque profil.

Dans l’onglet **Pulse**, choisissez un mode exclusif : **Pulse global** partage
une composition unique entre les profils autorisés, tandis que **Pulse par
profil** adapte la visibilité et l’ordre au profil sélectionné. Le même tableau
de catalogue est utilisé dans les deux modes. La limite est de six compteurs par
défaut et peut être réglée jusqu’à dix.

Le mode global peut être autorisé dans l’interface self-service. Cette option ne
contourne jamais le droit **Afficher Pulse général** du profil.

Les compteurs fondés sur une recherche sauvegardée commune utilisent uniquement
des recherches publiques. Les résultats restent filtrés par les droits, les
entités et la récursivité du technicien connecté.

Les compteurs marqués comme obligatoires préparent le futur mode hybride : ils
formeront le socle que les compléments personnels ne pourront pas masquer. Aucun
écran de personnalisation utilisateur n'est activé dans cette version.

### Installation

Téléchargez l'archive de release, puis déposez le dossier `brandpulse` dans le dossier `plugins` de GLPI.

```text
glpi/plugins/brandpulse
```

Activez ensuite le plugin depuis :

```text
Configuration > Plugins
```

### Compatibilité

- GLPI 11.x
- PHP 8.2 ou supérieur

GLPI 12 : préparation examinée sur les sources 12.0.1, compatibilité non encore
validée. La borne maximale reste strictement inférieure à 12.0.0.

### Notes d'utilisation

Pour créer des compteurs métier avancés, utilisez le moteur de recherche GLPI, sauvegardez votre recherche, puis sélectionnez-la dans l'onglet Pulse.

Les images importées depuis BrandPulse restent conservées par GLPI afin de survivre aux mises à jour du plugin.

### Sécurité

Réservez la configuration du plugin aux administrateurs GLPI de confiance et
effectuez une sauvegarde avant installation ou mise à jour. Consultez la
[politique de sécurité](SECURITY.md) pour le signalement responsable des
vulnérabilités et la [référence d’architecture et de sécurité](docs/ARCHITECTURE_SECURITY.md)
pour le modèle des routes, des droits et des données.

### Contribution

Les correctifs et améliorations ciblés sont les bienvenus. Consultez le
[guide de contribution](CONTRIBUTING.md) avant d'ouvrir une pull request.

### Développement et build

Le [guide de contribution](CONTRIBUTING.md) décrit les contrôles PHP, JavaScript,
Composer et les traductions. Construisez le package avec
`./scripts/build-release.sh` : la version est lue depuis `setup.php` et le résultat
est `dist/brandpulse-0.2.12.zip`, avec le dossier installable `dist/brandpulse/`.
Les fichiers locaux et les outils de développement sont exclus du ZIP.

### Inspirations et crédits

BrandPulse s'appuie sur l'expérience acquise avec plusieurs personnalisations
et extensions GLPI existantes autour de l'identité visuelle et des indicateurs
opérationnels. Le plugin actuel possède toutefois sa propre architecture, son
propre modèle de configuration, ses mécanismes de sécurité et ses intégrations
GLPI 11. Il est développé et maintenu comme un projet autonome par TiniSys IT
Solutions.

Le sélecteur d'icônes contient une sélection adaptée du Solar Icon Set. Les
attributions et conditions applicables sont détaillées dans
[THIRD_PARTY_NOTICES.md](THIRD_PARTY_NOTICES.md).

### Licence

GPL-3.0-or-later.

---

## English

### Overview

BrandPulse brings branding and operational visibility into one GLPI plugin with five configuration areas:

- **Brand**: customise logos, browser title, favicon, login background and visual identity.
- **Login alert**: show a styled information message on the login page, with an icon and simple formatting.
- **Pulse**: add quick counters to the GLPI header for tickets, tasks or saved searches.
- **Features**: configure additional tools, including direct access to asset web interfaces through their IP address.
- **Diagnostic**: check the installed version, enabled features and imported image files.

### Brand Features

- Change the browser tab title.
- Set a custom favicon.
- Configure GLPI sidebar logos.
- Use separate logo variants for light, dark and neutral themes.
- Customise the login page logo.
- Add a background image to the login page.
- Keep GLPI clean when the plugin is disabled or waiting for an update.

### Login Alert Features

- Display a message on the login page.
- Choose the alert type: info, warning, danger or success.
- Pick an icon from the bundled icon pack.
- Write short or longer messages.
- Open long messages by default when needed.
- Use a lightweight formatting toolbar: heading, bold, list, code and link.

### Pulse Features

- Add counters to the GLPI header.
- Choose each counter label, icon and colour.
- Enable or disable counters individually.
- Reorder counters.
- Use simple built-in presets.
- Use GLPI saved searches to create counters that match your organisation.
- Define warning and critical thresholds.
- Set the refresh interval.
- Recompute counters on page entry, after GLPI AJAX saves and when returning
  to the tab or a page restored from history, without reloading the page.
- Collapse the global GLPI search field into a magnifier icon.
- Allow General Pulse visibility profile by profile from the BrandPulse tab in GLPI profiles.
- Open an asset's web management interface directly from its main form, using
  the IP address recorded on its network ports (HTTPS or HTTP).

Use the **Columns to display** button on a counter row in the **Pulse** tab to configure its
ticket table. Turn off **Use GLPI preferences**, add and reorder columns, and
choose the sorting field and direction. Click **Apply**, then save the Pulse
configuration. GLPI keeps its fixed columns first. Settings belong to the counter
catalogue and apply to profiles using that counter without changing personal GLPI
preferences. Counter filters remain in place.

The **Header composition** panel separates displayed and available counters.
Add or remove counters, then reorder them using drag handles or keyboard-accessible
arrows. A moved counter is highlighted in both the panel and catalogue. The header
preview follows labels, colors, icons and order, with sample counts. In profile
mode, select the profile in this panel; unsaved changes are protected. Save the
Pulse configuration to apply the composition.

In **Pulse by profile** mode, **Authorize specific profiles** appears below the
mode choices in **Default settings**, using GLPI's native multi-select with profile
chips. Saving Pulse updates the existing native Pulse right for editable profiles;
read-only profiles and other rights remain unchanged. Native profile UPDATE rights
are required. Global mode keeps the separate permissions panel and Save action.

The composition profile dropdown only offers authorized profiles and follows the
native multi-selection immediately. Save permissions first when removing the loaded
profile or adding a newly authorized profile; the loaded composition context is
preserved. An empty authorization list is supported.

Composition, catalogue rows and ticket columns use GLPI's native sortable component,
as the rules do, with visible drop placeholders. Keyboard arrows remain available.
Fixed ticket columns follow GLPI's native order. Pulse grants no additional ticket
rights and self-service restrictions still apply.

### Profile-based Pulse visibility

General Pulse visibility can be enabled separately for each profile from:

```text
Administration > Profiles > [profile] > BrandPulse
```

The **Display General Pulse** right is enabled by default for standard-interface profiles and disabled for self-service profiles. Administrators can adjust this setting for each profile.

The BrandPulse **Pulse** tab offers two exclusive modes. **Global Pulse** shares
one composition across authorized profiles, while **Pulse by profile** applies
visibility and ordering to the selected profile. Both modes use the same counter
catalogue table. The default limit is six counters and can be raised to ten.

Global mode can also be enabled for the self-service interface. This option never
bypasses the profile's **Display General Pulse** permission.

Shared counters based on a saved search only accept public searches. Results are
still filtered by the current technician's rights, active entities and recursive
scope.

Counters marked as required prepare the future hybrid mode: they will form the
profile baseline that personal additions cannot hide. No user customisation UI is
enabled in this release.

### Installation

Download the release archive, then place the `brandpulse` folder in the GLPI `plugins` directory.

```text
glpi/plugins/brandpulse
```

Then enable the plugin from:

```text
Setup > Plugins
```

### Compatibility

- GLPI 11.x
- PHP 8.2 or higher

GLPI 12: preparation reviewed against the 12.0.1 sources; compatibility is not
yet validated. The maximum version remains strictly below 12.0.0.

### Usage Notes

For advanced operational counters, create a search in GLPI, save it, then select it in the Pulse tab.

Images imported through BrandPulse are kept in GLPI storage so they remain available across plugin updates.

### Security

Restrict plugin configuration to trusted GLPI administrators and create a
backup before installation or upgrade. See the [security policy](SECURITY.md)
for responsible vulnerability reporting and the
[architecture and security baseline](docs/ARCHITECTURE_SECURITY.md) for route,
permission and data models.

### Contributing

Focused fixes and improvements are welcome. Read the
[contribution guide](CONTRIBUTING.md) before opening a pull request.

### Development and Build

The [contribution guide](CONTRIBUTING.md) documents PHP, JavaScript, Composer
and translation checks. Run `./scripts/build-release.sh` to build the version
from `setup.php` as `dist/brandpulse-0.2.12.zip` and `dist/brandpulse/`.
Local files and development tooling are excluded from the archive.

### Inspirations and credits

BrandPulse builds on experience gained from several existing GLPI
customisations and extensions dealing with visual identity and operational
indicators. The current plugin nevertheless has its own architecture,
configuration model, security controls and GLPI 11 integrations. It is
developed and maintained as an independent project by TiniSys IT Solutions.

The icon picker contains an adapted selection from the Solar Icon Set.
Applicable attribution and licensing details are recorded in
[THIRD_PARTY_NOTICES.md](THIRD_PARTY_NOTICES.md).

### License

GPL-3.0-or-later.
