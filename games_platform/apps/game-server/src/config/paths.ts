import path from "node:path";
import { fileURLToPath } from "node:url";

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

export const SERVER_ROOT_DIR = path.resolve(__dirname, "../..");
export const WORKSPACE_ROOT_DIR = path.resolve(SERVER_ROOT_DIR, "../..");
export const REPO_ROOT_DIR = path.resolve(SERVER_ROOT_DIR, "../../..");
export const DEFAULT_PUZZLE_OUTPUT_DIR = path.join(
  REPO_ROOT_DIR,
  "image_puzzle_maker",
  "output"
);

