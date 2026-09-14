# GLPI BrandPulse

![GLPI 11](https://img.shields.io/badge/GLPI-11.x-blue)
![PHP 8.2+](https://img.shields.io/badge/PHP-8.2%2B-777bb4)
![License GPL-3.0](https://img.shields.io/badge/license-GPL--3.0--or--later-green)
![Status](https://img.shields.io/badge/status-active-success)

**GLPI BrandPulse** personnalise l'identité visuelle de GLPI et ajoute des compteurs rapides dans le header. Le plugin est pensé pour les équipes qui veulent une interface GLPI plus lisible, plus cohérente avec leur marque, et plus efficace au quotidien.

> English version below.

## Français

### Aperçu

BrandPulse regroupe quatre espaces de configuration :

- **Brand** : personnalisation des logos, du titre navigateur, du favicon, du fond de login et de l'identité visuelle.
- **Login alert** : message d'information visible sur la page de connexion, avec icône, mise en forme simple et affichage extensible.
- **Pulse** : compteurs dans le header GLPI pour suivre rapidement les tickets, les tâches ou des recherches sauvegardées.
- **Diagnostic** : informations utiles pour vérifier la version installée, l'état des options et les fichiers d'images importés.

### Captures d'écran

Les captures officielles seront ajoutées à cette section.

```text
Image à ajouter : page de configuration Brand
Image à ajouter : onglet Login alert
Image à ajouter : compteurs Pulse dans le header GLPI
Image à ajouter : page de login personnalisée
```

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
- Réduire la recherche globale GLPI à une icône loupe.
- Autoriser l'affichage de Pulse profil par profil depuis l'onglet BrandPulse des profils GLPI.

### Visibilité de Pulse selon le profil

L'affichage de Pulse général peut être autorisé séparément pour chaque profil depuis :

```text
Administration > Profils > [profil] > BrandPulse
```

Le droit **Afficher Pulse général** est activé par défaut pour les profils utilisant l'interface standard et désactivé pour les profils self-service. Un administrateur peut adapter ce choix à chaque profil.

La composition de Pulse peut également être définie dans l'onglet **Pulse** de
BrandPulse. Sélectionnez un profil GLPI, activez jusqu'à six compteurs par défaut
(limite réglable jusqu'à dix) et choisissez leur ordre. Le changement de profil
actif recharge automatiquement la
composition correspondante sans mélanger les autres profils de l'utilisateur.

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

### Notes d'utilisation

Pour créer des compteurs métier avancés, utilisez le moteur de recherche GLPI, sauvegardez votre recherche, puis sélectionnez-la dans l'onglet Pulse.

Les images importées depuis BrandPulse restent conservées par GLPI afin de survivre aux mises à jour du plugin.

### Sécurité

Réservez la configuration du plugin aux administrateurs GLPI de confiance et
effectuez une sauvegarde avant installation ou mise à jour. Consultez la
[politique de sécurité](SECURITY.md) pour le signalement responsable des
vulnérabilités.

### Contribution

Les correctifs et améliorations ciblés sont les bienvenus. Consultez le
[guide de contribution](CONTRIBUTING.md) avant d'ouvrir une pull request.

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

BrandPulse brings branding and operational visibility into one GLPI plugin:

- **Brand**: customise logos, browser title, favicon, login background and visual identity.
- **Login alert**: show a styled information message on the login page, with an icon and simple formatting.
- **Pulse**: add quick counters to the GLPI header for tickets, tasks or saved searches.
- **Diagnostic**: check the installed version, enabled features and imported image files.

### Screenshots

Official screenshots will be added to this section.

```text
Image to add: Brand configuration page
Image to add: Login alert tab
Image to add: Pulse counters in the GLPI header
Image to add: customised login page
```

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
- Collapse the global GLPI search field into a magnifier icon.
- Allow General Pulse visibility profile by profile from the BrandPulse tab in GLPI profiles.

### Profile-based Pulse visibility

General Pulse visibility can be enabled separately for each profile from:

```text
Administration > Profiles > [profile] > BrandPulse
```

The **Display General Pulse** right is enabled by default for standard-interface profiles and disabled for self-service profiles. Administrators can adjust this setting for each profile.

Pulse composition can also be configured from the BrandPulse **Pulse** tab.
Select a GLPI profile, enable up to six counters by default (adjustable up to ten)
and set their order. Switching the active profile reloads the matching
composition without merging the user's
other profiles.

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

### Usage Notes

For advanced operational counters, create a search in GLPI, save it, then select it in the Pulse tab.

Images imported through BrandPulse are kept in GLPI storage so they remain available across plugin updates.

### Security

Restrict plugin configuration to trusted GLPI administrators and create a
backup before installation or upgrade. See the [security policy](SECURITY.md)
for responsible vulnerability reporting.

### Contributing

Focused fixes and improvements are welcome. Read the
[contribution guide](CONTRIBUTING.md) before opening a pull request.

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
