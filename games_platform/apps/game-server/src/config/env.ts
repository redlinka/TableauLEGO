import path from "node:path";
import dotenv from "dotenv";
import { z } from "zod";
import { DEFAULT_PUZZLE_OUTPUT_DIR, SERVER_ROOT_DIR } from "./paths.js";

dotenv.config({ path: path.join(SERVER_ROOT_DIR, ".env") });

const envSchema = z.object({
  NODE_ENV: z.enum(["development", "test", "production"]).default("development"),
  HOST: z.string().default("127.0.0.1"),
  PORT: z.coerce.number().int().positive().default(3001),
  APP_VERSION: z.string().default("0.1.0"),
  CORS_ORIGIN: z.string().default("http://127.0.0.1:5173"),
  MONGODB_URI: z.string().default("mongodb://127.0.0.1:27017/tableaulego_games"),
  MONGODB_DB_NAME: z.string().default("tableaulego_games"),
  GAME_TOKEN_SECRET: z.string().min(8).default("change-me-for-development"),
  PHP_GAME_TOKEN_SECRET: z.string().refine(
    (val) => val === "" || val.length >= 32,
    { message: "PHP_GAME_TOKEN_SECRET must be empty (disabled) or at least 32 characters." }
  ).default(""),
  TOKEN_ISSUER: z.string().default("tableaulego-game-server"),
  TOKEN_AUDIENCE: z.string().default("tableaulego-games"),
  PHP_TOKEN_ISSUER: z.string().default("tableaulego-php"),
  PHP_TOKEN_AUDIENCE: z.string().default("tableaulego-games"),
  GUEST_TOKEN_TTL_SECONDS: z.coerce.number().int().positive().default(604800),
  STATIC_PREVIEW_PREFIX: z.string().default("/assets/puzzle-previews/"),
  WS_PATH: z.string().default("/ws"),
  PUBLIC_BASE_URL: z.string().optional().default(""),
  PUBLIC_WS_URL: z.string().optional().default(""),
  PUZZLE_OUTPUT_DIR: z.string().optional().default(DEFAULT_PUZZLE_OUTPUT_DIR),
  SOLO_TURN_LIMIT_MS: z.coerce.number().int().positive().default(30000),
  DUPLICATE_TURN_LIMIT_MS: z.coerce.number().int().positive().default(30000),
  DUPLICATE_RECONNECT_GRACE_MS: z.coerce.number().int().positive().default(60000),
  DUPLICATE_CHAT_MAX_LENGTH: z.coerce.number().int().positive().default(240),
  IMAGE_REBUILD_MAX_SEQUENCE_LENGTH: z.coerce.number().int().positive().default(160),
  LINE_BREAKER_MAX_SEQUENCE_LENGTH: z.coerce.number().int().positive().default(200)
});

export type AppConfig = {
  nodeEnv: "development" | "test" | "production";
  host: string;
  port: number;
  appVersion: string;
  corsOrigin: string;
  mongodbUri: string;
  mongodbDbName: string;
  gameTokenSecret: string;
  phpGameTokenSecret: string;
  tokenIssuer: string;
  tokenAudience: string;
  phpTokenIssuer: string;
  phpTokenAudience: string;
  guestTokenTtlSeconds: number;
  staticPreviewPrefix: string;
  wsPath: string;
  publicBaseUrl?: string;
  publicWsUrl?: string;
  puzzleOutputDir: string;
  soloTurnLimitMs: number;
  duplicateTurnLimitMs: number;
  duplicateReconnectGraceMs: number;
  duplicateChatMaxLength: number;
  imageRebuildMaxSequenceLength: number;
  lineBreakerMaxSequenceLength: number;
};

export function loadConfig(): AppConfig {
  const parsed = envSchema.parse(process.env);

  return {
    nodeEnv: parsed.NODE_ENV,
    host: parsed.HOST,
    port: parsed.PORT,
    appVersion: parsed.APP_VERSION,
    corsOrigin: parsed.CORS_ORIGIN,
    mongodbUri: parsed.MONGODB_URI,
    mongodbDbName: parsed.MONGODB_DB_NAME,
    gameTokenSecret: parsed.GAME_TOKEN_SECRET,
    phpGameTokenSecret: parsed.PHP_GAME_TOKEN_SECRET,
    tokenIssuer: parsed.TOKEN_ISSUER,
    tokenAudience: parsed.TOKEN_AUDIENCE,
    phpTokenIssuer: parsed.PHP_TOKEN_ISSUER,
    phpTokenAudience: parsed.PHP_TOKEN_AUDIENCE,
    guestTokenTtlSeconds: parsed.GUEST_TOKEN_TTL_SECONDS,
    staticPreviewPrefix: parsed.STATIC_PREVIEW_PREFIX,
    wsPath: parsed.WS_PATH,
    publicBaseUrl: parsed.PUBLIC_BASE_URL || undefined,
    publicWsUrl: parsed.PUBLIC_WS_URL || undefined,
    puzzleOutputDir: parsed.PUZZLE_OUTPUT_DIR,
    soloTurnLimitMs: parsed.SOLO_TURN_LIMIT_MS,
    duplicateTurnLimitMs: parsed.DUPLICATE_TURN_LIMIT_MS,
    duplicateReconnectGraceMs: parsed.DUPLICATE_RECONNECT_GRACE_MS,
    duplicateChatMaxLength: parsed.DUPLICATE_CHAT_MAX_LENGTH,
    imageRebuildMaxSequenceLength: parsed.IMAGE_REBUILD_MAX_SEQUENCE_LENGTH,
    lineBreakerMaxSequenceLength: parsed.LINE_BREAKER_MAX_SEQUENCE_LENGTH
  };
}
