# prosilver SE Appearance

prosilver SE Appearance adds an ACP page to customize the colors and typography of **prosilver Special Edition**. It targets the installed style folder `styles/prosilver_se/`, using the `overall_header_stylesheets_after` and `simple_header_stylesheets_after` events already present in the style.

This extension is independent from Revolution Appearance (`mundophpbb/revolutionappearance`). Both can be installed at the same time; each one only changes its own style.

## Installation

1. Keep prosilver Special Edition installed in `styles/prosilver_se/` (it requires its parent, `prosilver`).
2. Extract this archive into your forum's `ext/` directory. The extension manifest belongs at `ext/mundophpbb/seappearance/composer.json`.
3. Enable **prosilver SE Appearance** in **ACP → Customise → Manage extensions**.
4. Open **ACP → Extensions → prosilver SE Appearance → Colors and Fonts**. The page requires the board settings permission.
5. Select **Apply custom appearance**, choose your colors and fonts, then submit. Open the forum with prosilver Special Edition selected to see the result.

Empty fields inherit the style's originals. Load style defaults and submit to clear existing overrides. To pause customization while keeping saved choices, turn off **Apply custom appearance** and submit. The preview loads the installed prosilver SE stylesheet over prosilver and shows the current choices on sample forum content.

## What can be customized

- Page background, frame border, frame shadow and inner background.
- Header background (replaces the gray gradient) and header text.
- Category bars and topic list headers (replaces the gradient; also applied to jumpbox category links) and their text.
- Navigation bar background.
- Forum and topic rows. The lighter `bg3` rows, hover highlights and jumpbox sub-links follow the chosen row color.
- General text and messages, and links.
- General font, heading font, base size and message text size.

prosilver SE removes the frame border and shadow on screens of 920px or less; the custom shadow follows the same rule.

The general font also applies to message text in topics. Heading fonts are configured separately.

When a custom page background is set, the public footer credits and links automatically use contrasting colors. Footer links are underlined so they stay recognizable when the text and link colors match.

Montserrat is bundled as variable WOFF2 files under the SIL Open Font License included in `assets/fonts/OFL.txt`. The other built-in choices use fonts already installed on the visitor's device and their fallback stacks.

## Themes

The **Themes** card at the top of **Colors and Fonts** fills the form for you. Nothing changes on the forum until you submit.

- **Ready-made themes:** Ocean, Forest, Sepia paper, Graphite, Wine and High contrast. Choose one, select **Apply to form**, adjust anything you like and submit. All themes keep light inner surfaces, because the extension does not recolor post panels or forms; every text and link color meets a 4.5:1 contrast ratio against its background.
- **Your themes:** enter a name and select **Save theme** to keep the colors, fonts and sizes currently in the form, including changes you have not submitted. Saving under an existing name (case-insensitive) updates that theme. Up to 20 themes can be kept. Saving a theme does not change the forum settings.
- **Export and import:** **Export form (.json)** downloads the current form as a theme file. **Import theme (.json)** loads such a file into the form, for example to move a look from a test board to a live one. Imported values are checked in the browser and again by the server when you submit or save.

If a theme uses a custom font that is later removed, or an imported theme names a font that does not exist on this board, the style's default font is used instead and the ACP says so.

## Custom fonts

In **Colors and Fonts → Custom fonts**, enter a display name and upload a WOFF2 file of up to 1 MiB that you are licensed to use on the web. Select the new font in either typography menu to see it in the preview. Submit the appearance settings to apply the selection to the forum.

Each upload defines one regular face. Browsers can synthesize bold and italic when needed. Custom fonts are stored with the extension's database data and served by your phpBB installation. To remove a font that is selected in either typography menu, first save a different selection.

## Updating

Disable the extension, replace its files in `ext/mundophpbb/seappearance/`, purge the board cache from the ACP, and enable it again. Keep the extension data to retain your settings, saved themes and custom fonts. Updating from 1.0.x adds the storage for saved themes automatically when the extension is enabled again.

## Changelog

### 1.1.0
- Added six ready-made themes, saving the form as named themes, and theme export/import as JSON.
