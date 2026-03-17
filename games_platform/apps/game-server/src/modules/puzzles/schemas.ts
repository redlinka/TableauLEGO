import { z } from "zod";

export const puzzleManifestEntrySchema = z.object({
  id: z.string().min(1),
  name: z.string().min(1),
  json: z.string().min(1),
  previewImage: z.string().min(1),
  difficulty: z.string().min(1)
});

export const puzzleManifestSchema = z.object({
  version: z.number().int().positive(),
  gridWidth: z.number().int().positive(),
  gridHeight: z.number().int().positive(),
  images: z.array(puzzleManifestEntrySchema)
});

export const puzzleTargetSchema = z.object({
  id: z.string().min(1),
  name: z.string().min(1),
  gridWidth: z.number().int().positive(),
  gridHeight: z.number().int().positive(),
  palette: z.array(z.string().min(1)),
  paletteColors: z.record(z.string(), z.string()).optional(),
  cells: z.array(z.array(z.string().min(1))),
  previewImage: z.string().min(1),
  sourceImage: z.string().min(1),
  difficulty: z.string().min(1)
});

