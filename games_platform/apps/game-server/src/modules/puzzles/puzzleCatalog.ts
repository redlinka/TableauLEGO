import fs from "node:fs/promises";
import path from "node:path";
import { PuzzleSummary, PuzzleTarget } from "@games-platform/game-contracts";
import { puzzleManifestSchema, puzzleTargetSchema } from "./schemas.js";

type LoadedPuzzle = PuzzleTarget & {
  jsonFile: string;
};

export class PuzzleCatalog {
  private manifestVersion = 0;
  private readonly puzzles = new Map<string, LoadedPuzzle>();
  private readonly outputDir: string;
  private readonly jsonDir: string;
  private readonly previewDir: string;
  readonly previewPrefix: string;

  constructor(options: {
    outputDir: string;
    previewPrefix: string;
  }) {
    this.outputDir = options.outputDir;
    this.jsonDir = path.join(options.outputDir, "json");
    this.previewDir = path.join(options.outputDir, "previews");
    this.previewPrefix = options.previewPrefix.endsWith("/")
      ? options.previewPrefix
      : `${options.previewPrefix}/`;
  }

  get size(): number {
    return this.puzzles.size;
  }

  get previewsDirectory(): string {
    return this.previewDir;
  }

  async load(): Promise<void> {
    const manifestPath = path.join(this.outputDir, "manifest.json");
    const manifestRaw = await fs.readFile(manifestPath, "utf8");
    const manifest = puzzleManifestSchema.parse(JSON.parse(manifestRaw));

    this.manifestVersion = manifest.version;
    this.puzzles.clear();

    for (const manifestEntry of manifest.images) {
      const jsonPath = path.join(this.jsonDir, manifestEntry.json);
      const previewPath = path.join(this.previewDir, manifestEntry.previewImage);
      const puzzleRaw = await fs.readFile(jsonPath, "utf8");
      const parsedPuzzle = puzzleTargetSchema.parse(JSON.parse(puzzleRaw));

      if (parsedPuzzle.id !== manifestEntry.id) {
        throw new Error(
          `Puzzle id mismatch for ${manifestEntry.json}: manifest=${manifestEntry.id} json=${parsedPuzzle.id}`
        );
      }

      if (parsedPuzzle.previewImage !== manifestEntry.previewImage) {
        throw new Error(
          `Preview mismatch for ${manifestEntry.id}: manifest=${manifestEntry.previewImage} json=${parsedPuzzle.previewImage}`
        );
      }

      if (
        parsedPuzzle.cells.length !== parsedPuzzle.gridHeight ||
        parsedPuzzle.cells.some((row) => row.length !== parsedPuzzle.gridWidth)
      ) {
        throw new Error(`Invalid grid dimensions for puzzle ${parsedPuzzle.id}`);
      }

      await fs.access(previewPath);

      this.puzzles.set(parsedPuzzle.id, {
        ...parsedPuzzle,
        jsonFile: manifestEntry.json
      });
    }
  }

  list(baseUrl: string): PuzzleSummary[] {
    return [...this.puzzles.values()]
      .sort((left, right) => left.id.localeCompare(right.id))
      .map((puzzle) => ({
        id: puzzle.id,
        name: puzzle.name,
        gridWidth: puzzle.gridWidth,
        gridHeight: puzzle.gridHeight,
        difficulty: puzzle.difficulty,
        previewImage: puzzle.previewImage,
        previewUrl: this.buildPreviewUrl(puzzle.previewImage, baseUrl)
      }));
  }

  getById(id: string, baseUrl: string): (PuzzleTarget & { previewUrl: string }) | null {
    const found = this.puzzles.get(id);

    if (!found) {
      return null;
    }

    return {
      ...found,
      previewUrl: this.buildPreviewUrl(found.previewImage, baseUrl)
    };
  }

  getTarget(id: string): PuzzleTarget | null {
    const found = this.puzzles.get(id);

    if (!found) {
      return null;
    }

    return {
      id: found.id,
      name: found.name,
      gridWidth: found.gridWidth,
      gridHeight: found.gridHeight,
      palette: [...found.palette],
      paletteColors: found.paletteColors ? { ...found.paletteColors } : undefined,
      cells: found.cells.map((row) => [...row]),
      previewImage: found.previewImage,
      sourceImage: found.sourceImage,
      difficulty: found.difficulty
    };
  }

  buildPreviewUrl(previewImage: string, baseUrl: string): string {
    return `${baseUrl}${this.previewPrefix}${previewImage}`;
  }

  getManifestVersion(): number {
    return this.manifestVersion;
  }
}
