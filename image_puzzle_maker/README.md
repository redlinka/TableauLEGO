# Image Puzzle Maker

This folder contains a standalone batch pipeline that turns source images into
10x10 assets for a LEGO-like image reproduction puzzle.

## Structure

```text
image_puzzle_maker/
  input/                # Put source images here
  output/
    previews/           # Generated preview PNG files
    json/               # One JSON file per source image
    debug/              # Normalized square images used for debugging
    manifest.json       # Global manifest generated on each run
  scripts/
    config.mjs          # Fixed grid, palette and pipeline constants
    build-targets.mjs   # Main batch generation script
  .gitignore
  package.json
```

## Accepted input formats

Put raw images in `image_puzzle_maker/input/`.

Supported extensions:

- `png`
- `jpg`
- `jpeg`
- `webp`

Unsupported files are skipped with an explicit log message.

## Install and run

From `image_puzzle_maker/`:

```bash
npm install
npm run build-targets
```

The command scans every supported image in `input/` and updates:

- `output/previews/`
- `output/json/`
- `output/debug/`
- `output/manifest.json`

The script is relaunchable and deterministic:

- same input set -> same output file names
- same images -> same 10x10 grids
- existing generated files are overwritten in place
- stale files from removed inputs are not deleted automatically

## Pipeline summary

Each image goes through the same fixed steps:

1. load the image and apply EXIF orientation
2. flatten transparency on white and convert to RGB
3. center-crop to a square while resizing to a normalized working image
4. reduce the image into a logical 10x10 grid
5. compute the average color of each cell
6. map each cell to the nearest color in the LEGO-like palette
7. generate the final `cells` matrix
8. export a scaled preview PNG from the 10x10 matrix
9. export one JSON file for the image
10. rebuild `output/manifest.json`

## Output naming rules

The base id comes from the original file name without extension.

Normalization rules:

- lowercase only
- spaces and separators become `_`
- problematic characters are removed
- duplicate normalized ids receive a numeric suffix such as `_2`

Examples:

- `Pikachu.png` -> `pikachu_preview.png` and `pikachu_10x10.json`
- `My Image.webp` -> `my_image_preview.png` and `my_image_10x10.json`

## JSON format

Each generated JSON file in `output/json/` contains at least:

- `id`
- `name`
- `gridWidth`
- `gridHeight`
- `palette`
- `cells`
- `previewImage`
- `sourceImage`
- `difficulty`

This pipeline also includes `paletteColors` to expose the hex code behind each
palette name for easier game integration.

## Manifest format

`output/manifest.json` contains:

- `version`
- `gridWidth`
- `gridHeight`
- `images`

Each entry in `images` contains:

- `id`
- `name`
- `json`
- `previewImage`
- `difficulty`

## Preview and debug outputs

- `output/previews/` contains enlarged PNG previews built directly from the
  simplified 10x10 grid.
- `output/debug/` contains normalized square PNG files before palette mapping.

## LEGO-like palette

The palette is intentionally limited, stable and easy to consume in-game.

| Name | Hex |
| --- | --- |
| `white` | `#F4F4F4` |
| `black` | `#1B1B1B` |
| `light_gray` | `#B0B7C1` |
| `dark_gray` | `#5A6572` |
| `red` | `#C91A09` |
| `orange` | `#FE8A18` |
| `yellow` | `#F2CD37` |
| `green` | `#237841` |
| `blue` | `#0055BF` |
| `light_blue` | `#7DBFDD` |
| `tan` | `#DEC69C` |
| `brown` | `#6B4423` |

Nearest-color matching uses CIE Lab distance for more stable palette selection
than a plain RGB comparison.

## Difficulty heuristic

Difficulty is estimated from:

- the number of unique palette colors present in the 10x10 result
- the number of color transitions between adjacent cells

The output label is one of:

- `easy`
- `medium`
- `hard`
