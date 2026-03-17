import fs from "node:fs/promises";
import path from "node:path";
import { fileURLToPath } from "node:url";
import sharp from "sharp";
import {
  GRID_WIDTH,
  GRID_HEIGHT,
  MANIFEST_VERSION,
  PALETTE,
  PREVIEW_SCALE,
  PREVIEW_SIZE,
  SUPPORTED_EXTENSIONS,
  WORKING_CELL_SIZE,
  WORKING_SIZE
} from "./config.mjs";

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const ROOT_DIR = path.resolve(__dirname, "..");
const INPUT_DIR = path.join(ROOT_DIR, "input");
const OUTPUT_DIR = path.join(ROOT_DIR, "output");
const PREVIEW_DIR = path.join(OUTPUT_DIR, "previews");
const JSON_DIR = path.join(OUTPUT_DIR, "json");
const DEBUG_DIR = path.join(OUTPUT_DIR, "debug");
const MANIFEST_PATH = path.join(OUTPUT_DIR, "manifest.json");

async function main() {
  await ensureDirectories();

  const paletteIndex = buildPaletteIndex(PALETTE);
  const sourceFiles = await collectSourceFiles();
  const tasks = buildTasks(sourceFiles);
  const manifestImages = [];
  let hadFailures = false;

  if (tasks.length === 0) {
    await writeManifest([]);
    console.log("[info] No supported images found in input/. Empty manifest written.");
    return;
  }

  for (const task of tasks) {
    try {
      const manifestEntry = await processImage(task, paletteIndex);
      manifestImages.push(manifestEntry);
      console.log(`[ok] ${task.fileName} -> ${manifestEntry.json}`);
    } catch (error) {
      hadFailures = true;
      console.error(`[error] ${task.fileName}: ${error.message}`);
    }
  }

  manifestImages.sort((left, right) => left.id.localeCompare(right.id));
  await writeManifest(manifestImages);

  console.log(
    `[done] ${manifestImages.length} image(s) processed. Manifest written to output/manifest.json.`
  );

  if (hadFailures) {
    process.exitCode = 1;
  }
}

async function ensureDirectories() {
  await Promise.all([
    fs.mkdir(INPUT_DIR, { recursive: true }),
    fs.mkdir(PREVIEW_DIR, { recursive: true }),
    fs.mkdir(JSON_DIR, { recursive: true }),
    fs.mkdir(DEBUG_DIR, { recursive: true })
  ]);
}

async function collectSourceFiles() {
  const entries = await fs.readdir(INPUT_DIR, { withFileTypes: true });
  const sortedEntries = [...entries].sort((left, right) =>
    left.name.localeCompare(right.name, undefined, { sensitivity: "base" })
  );
  const supportedFiles = [];

  for (const entry of sortedEntries) {
    if (!entry.isFile()) {
      console.log(`[skip] Ignored non-file entry: ${entry.name}`);
      continue;
    }

    const extension = path.extname(entry.name).toLowerCase();
    if (!SUPPORTED_EXTENSIONS.has(extension)) {
      console.log(`[skip] Unsupported file extension: ${entry.name}`);
      continue;
    }

    supportedFiles.push(entry.name);
  }

  return supportedFiles;
}

function buildTasks(fileNames) {
  const idCounts = new Map();

  return fileNames.map((fileName) => {
    const parsed = path.parse(fileName);
    const baseId = normalizeId(parsed.name);
    const duplicateCount = (idCounts.get(baseId) ?? 0) + 1;
    idCounts.set(baseId, duplicateCount);

    const id = duplicateCount === 1 ? baseId : `${baseId}_${duplicateCount}`;
    const previewImage = `${id}_preview.png`;
    const jsonFile = `${id}_${GRID_WIDTH}x${GRID_HEIGHT}.json`;
    const debugImage = `${id}_normalized.png`;

    return {
      id,
      name: toDisplayName(parsed.name),
      fileName,
      sourcePath: path.join(INPUT_DIR, fileName),
      previewImage,
      jsonFile,
      debugImage
    };
  });
}

async function processImage(task, paletteIndex) {
  const normalizedImage = sharp(task.sourcePath)
    .rotate()
    .flatten({ background: "#FFFFFF" })
    .removeAlpha()
    .toColorspace("srgb")
    .resize(WORKING_SIZE, WORKING_SIZE, {
      fit: "cover",
      position: "centre",
      kernel: sharp.kernel.lanczos3
    });

  const [{ data, info }, debugBuffer] = await Promise.all([
    normalizedImage.clone().raw().toBuffer({ resolveWithObject: true }),
    normalizedImage.clone().png().toBuffer()
  ]);

  if (info.channels !== 3) {
    throw new Error(`Expected 3 channels after normalization, got ${info.channels}`);
  }

  const averageCells = calculateCellAverages(data, info.width, info.height);
  const cells = averageCells.map((row) =>
    row.map((averageColor) => findNearestPaletteColor(averageColor, paletteIndex).name)
  );

  const difficulty = estimateDifficulty(cells);
  const previewBuffer = await createPreviewBuffer(cells, paletteIndex);

  const jsonPayload = {
    id: task.id,
    name: task.name,
    gridWidth: GRID_WIDTH,
    gridHeight: GRID_HEIGHT,
    palette: paletteIndex.map((entry) => entry.name),
    paletteColors: Object.fromEntries(
      paletteIndex.map((entry) => [entry.name, entry.hex])
    ),
    cells,
    previewImage: task.previewImage,
    sourceImage: task.fileName,
    difficulty
  };

  await Promise.all([
    fs.writeFile(path.join(DEBUG_DIR, task.debugImage), debugBuffer),
    fs.writeFile(path.join(PREVIEW_DIR, task.previewImage), previewBuffer),
    fs.writeFile(path.join(JSON_DIR, task.jsonFile), `${JSON.stringify(jsonPayload, null, 2)}\n`)
  ]);

  return {
    id: task.id,
    name: task.name,
    json: task.jsonFile,
    previewImage: task.previewImage,
    difficulty
  };
}

function calculateCellAverages(data, width, height) {
  const cellWidth = Math.floor(width / GRID_WIDTH);
  const cellHeight = Math.floor(height / GRID_HEIGHT);
  const rows = [];

  for (let cellY = 0; cellY < GRID_HEIGHT; cellY += 1) {
    const row = [];

    for (let cellX = 0; cellX < GRID_WIDTH; cellX += 1) {
      let red = 0;
      let green = 0;
      let blue = 0;
      let samples = 0;

      for (let y = cellY * cellHeight; y < (cellY + 1) * cellHeight; y += 1) {
        for (let x = cellX * cellWidth; x < (cellX + 1) * cellWidth; x += 1) {
          const offset = (y * width + x) * 3;
          red += data[offset];
          green += data[offset + 1];
          blue += data[offset + 2];
          samples += 1;
        }
      }

      row.push({
        r: Math.round(red / samples),
        g: Math.round(green / samples),
        b: Math.round(blue / samples)
      });
    }

    rows.push(row);
  }

  return rows;
}

function buildPaletteIndex(palette) {
  return palette.map((entry) => {
    const rgb = hexToRgb(entry.hex);
    return {
      ...entry,
      rgb,
      lab: rgbToLab(rgb)
    };
  });
}

function findNearestPaletteColor(color, paletteIndex) {
  const targetLab = rgbToLab(color);
  let bestMatch = paletteIndex[0];
  let bestDistance = Number.POSITIVE_INFINITY;

  for (const candidate of paletteIndex) {
    const distance = deltaE76(targetLab, candidate.lab);

    if (distance < bestDistance) {
      bestDistance = distance;
      bestMatch = candidate;
    }
  }

  return bestMatch;
}

async function createPreviewBuffer(cells, paletteIndex) {
  const paletteByName = new Map(paletteIndex.map((entry) => [entry.name, entry.rgb]));
  const rawBuffer = Buffer.alloc(GRID_WIDTH * GRID_HEIGHT * 3);
  let offset = 0;

  for (let y = 0; y < GRID_HEIGHT; y += 1) {
    for (let x = 0; x < GRID_WIDTH; x += 1) {
      const rgb = paletteByName.get(cells[y][x]);
      rawBuffer[offset] = rgb.r;
      rawBuffer[offset + 1] = rgb.g;
      rawBuffer[offset + 2] = rgb.b;
      offset += 3;
    }
  }

  const gridOverlay = Buffer.from(createGridOverlaySvg());

  return sharp(rawBuffer, {
    raw: {
      width: GRID_WIDTH,
      height: GRID_HEIGHT,
      channels: 3
    }
  })
    .resize(PREVIEW_SIZE, PREVIEW_SIZE, { kernel: sharp.kernel.nearest })
    .composite([{ input: gridOverlay }])
    .png()
    .toBuffer();
}

function createGridOverlaySvg() {
  const lines = [];

  for (let index = 0; index <= GRID_WIDTH; index += 1) {
    const position = index * PREVIEW_SCALE;
    lines.push(
      `<line x1="${position}" y1="0" x2="${position}" y2="${PREVIEW_SIZE}" />`
    );
  }

  for (let index = 0; index <= GRID_HEIGHT; index += 1) {
    const position = index * PREVIEW_SCALE;
    lines.push(
      `<line x1="0" y1="${position}" x2="${PREVIEW_SIZE}" y2="${position}" />`
    );
  }

  return [
    `<svg xmlns="http://www.w3.org/2000/svg" width="${PREVIEW_SIZE}" height="${PREVIEW_SIZE}">`,
    '<g stroke="rgba(0,0,0,0.35)" stroke-width="1" shape-rendering="crispEdges">',
    ...lines,
    "</g>",
    "</svg>"
  ].join("");
}

function estimateDifficulty(cells) {
  const uniqueColors = new Set(cells.flat()).size;
  let transitions = 0;

  for (let y = 0; y < GRID_HEIGHT; y += 1) {
    for (let x = 0; x < GRID_WIDTH - 1; x += 1) {
      if (cells[y][x] !== cells[y][x + 1]) {
        transitions += 1;
      }
    }
  }

  for (let y = 0; y < GRID_HEIGHT - 1; y += 1) {
    for (let x = 0; x < GRID_WIDTH; x += 1) {
      if (cells[y][x] !== cells[y + 1][x]) {
        transitions += 1;
      }
    }
  }

  const score = uniqueColors * 12 + transitions;

  if (score <= 80) {
    return "easy";
  }

  if (score <= 120) {
    return "medium";
  }

  return "hard";
}

async function writeManifest(images) {
  const manifest = {
    version: MANIFEST_VERSION,
    gridWidth: GRID_WIDTH,
    gridHeight: GRID_HEIGHT,
    images
  };

  await fs.writeFile(MANIFEST_PATH, `${JSON.stringify(manifest, null, 2)}\n`);
}

function normalizeId(value) {
  const asciiValue = value.normalize("NFD").replace(/[\u0300-\u036f]/g, "");
  const collapsed = asciiValue
    .toLowerCase()
    .replace(/['"`]/g, "")
    .replace(/[^a-z0-9]+/g, "_")
    .replace(/^_+|_+$/g, "")
    .replace(/_+/g, "_");

  return collapsed || "image";
}

function toDisplayName(value) {
  const cleaned = value
    .replace(/[_-]+/g, " ")
    .replace(/\s+/g, " ")
    .trim();

  if (!cleaned) {
    return "Image";
  }

  return cleaned
    .split(" ")
    .map((segment) => segment.charAt(0).toUpperCase() + segment.slice(1).toLowerCase())
    .join(" ");
}

function hexToRgb(hex) {
  const normalized = hex.replace("#", "");
  return {
    r: Number.parseInt(normalized.slice(0, 2), 16),
    g: Number.parseInt(normalized.slice(2, 4), 16),
    b: Number.parseInt(normalized.slice(4, 6), 16)
  };
}

function rgbToLab(rgb) {
  const { x, y, z } = rgbToXyz(rgb);
  const whitePoint = { x: 95.047, y: 100, z: 108.883 };

  const scaledX = xyzPivot(x / whitePoint.x);
  const scaledY = xyzPivot(y / whitePoint.y);
  const scaledZ = xyzPivot(z / whitePoint.z);

  return {
    l: 116 * scaledY - 16,
    a: 500 * (scaledX - scaledY),
    b: 200 * (scaledY - scaledZ)
  };
}

function rgbToXyz({ r, g, b }) {
  const linearR = srgbToLinear(r / 255);
  const linearG = srgbToLinear(g / 255);
  const linearB = srgbToLinear(b / 255);

  return {
    x: (linearR * 0.4124 + linearG * 0.3576 + linearB * 0.1805) * 100,
    y: (linearR * 0.2126 + linearG * 0.7152 + linearB * 0.0722) * 100,
    z: (linearR * 0.0193 + linearG * 0.1192 + linearB * 0.9505) * 100
  };
}

function srgbToLinear(value) {
  if (value <= 0.04045) {
    return value / 12.92;
  }

  return ((value + 0.055) / 1.055) ** 2.4;
}

function xyzPivot(value) {
  if (value > 0.008856) {
    return value ** (1 / 3);
  }

  return 7.787 * value + 16 / 116;
}

function deltaE76(left, right) {
  return Math.sqrt(
    (left.l - right.l) ** 2 +
      (left.a - right.a) ** 2 +
      (left.b - right.b) ** 2
  );
}

main().catch((error) => {
  console.error(`[fatal] ${error.message}`);
  process.exitCode = 1;
});

