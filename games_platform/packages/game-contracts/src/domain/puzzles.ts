export interface PuzzleManifestEntry {
  id: string;
  name: string;
  json: string;
  previewImage: string;
  difficulty: string;
}

export interface PuzzleManifest {
  version: number;
  gridWidth: number;
  gridHeight: number;
  images: PuzzleManifestEntry[];
}

export interface PuzzleTarget {
  id: string;
  name: string;
  gridWidth: number;
  gridHeight: number;
  palette: string[];
  paletteColors?: Record<string, string>;
  cells: string[][];
  previewImage: string;
  sourceImage: string;
  difficulty: string;
}

export interface PuzzleSummary {
  id: string;
  name: string;
  gridWidth: number;
  gridHeight: number;
  difficulty: string;
  previewImage: string;
  previewUrl: string;
}

