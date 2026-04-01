import { BrickShapeDefinition } from "../domain/bricks.js";
import { GameSessionSummary, TechnicalPlayerIdentity } from "../domain/common.js";
import { HistoryEntry } from "../domain/history.js";
import { ImageRebuildSessionState, PlacementAttemptResponse } from "../domain/imageRebuild.js";
import {
  LineBreakerPlacementAttemptResponse,
  LineBreakerSessionState
} from "../domain/lineBreaker.js";
import { PuzzleSummary, PuzzleTarget } from "../domain/puzzles.js";
import { AuthSource, GameMode, GameType } from "../enums.js";
import { LoyaltyLedgerEntry, LoyaltySummary } from "../domain/history.js";

export interface ApiErrorResponse {
  error: {
    code: string;
    message: string;
    details?: unknown;
  };
}

export interface HealthResponse {
  status: "ok";
  service: string;
  version: string;
  serverTime: string;
  database: {
    state: "disconnected" | "connected" | "connecting" | "disconnecting";
  };
  puzzlesLoaded: number;
}

export interface GuestAuthRequest {
  preferredAlias?: string;
}

export interface GuestAuthResponse {
  accessToken: string;
  tokenType: "Bearer";
  expiresAt: string;
  player: TechnicalPlayerIdentity;
}

export interface PhpExchangeRequest {
  phpToken: string;
}

export interface PhpExchangeResponse {
  accessToken: string;
  tokenType: "Bearer";
  expiresAt: string;
  player: TechnicalPlayerIdentity;
  bridge: {
    issuer: string;
    audience: string;
    subjectHash: string;
    technicalLoyaltyId?: string;
  };
}

export interface BootstrapResponse {
  service: {
    name: string;
    version: string;
    environment: string;
    serverTime: string;
    websocketUrl: string;
  };
  auth: {
    supportedSources: AuthSource[];
    guestEnabled: boolean;
    phpTokenValidationConfigured: boolean;
    currentPlayer?: TechnicalPlayerIdentity | null;
  };
  games: {
    type: GameType;
    label: string;
    supportedModes: GameMode[];
  }[];
  puzzles: {
    total: number;
    items: PuzzleSummary[];
  };
  bricks: {
    totalShapes: number;
    shapes: BrickShapeDefinition[];
  };
  loyalty: {
    tiers: LoyaltyTier[];
    maxDiscountPercent: number;
  };
}

export interface PuzzleListResponse {
  total: number;
  items: PuzzleSummary[];
}

export interface PuzzleDetailResponse {
  item: PuzzleTarget & {
    previewUrl: string;
  };
}

export interface HistoryResponse {
  total: number;
  items: HistoryEntry[];
  filters?: {
    gameType?: GameType;
    mode?: GameMode;
    limit: number;
  };
}

export interface HistoryDetailResponse {
  item: HistoryEntry;
  session?: GameSessionSummary;
  result?: unknown;
  loyaltyEntries: LoyaltyLedgerEntry[];
}

export interface LoyaltyTier {
  points: number;
  discountPercent: number;
}

export interface LoyaltyBalanceResponse {
  summary: LoyaltySummary;
  recentEntries: LoyaltyLedgerEntry[];
}

export interface LoyaltyLedgerResponse {
  total: number;
  items: LoyaltyLedgerEntry[];
  summary: LoyaltySummary;
}

export interface LoyaltyRedeemRequest {
  points: number;
  orderRef?: string;
}

export interface LoyaltyRedeemResponse {
  success: boolean;
  pointsDeducted: number;
  discountPercent: number;
  balanceAfter: number;
}

export interface CreateSoloImageRebuildSessionRequest {
  puzzleId: string;
  seed?: string;
}

export interface SoloImageRebuildSessionResponse {
  state: ImageRebuildSessionState;
}

export interface SubmitSoloImageRebuildPlacementRequest {
  anchorX: number;
  anchorY: number;
  rotation: number;
}

export interface SubmitSoloImageRebuildPlacementResponse
  extends PlacementAttemptResponse {}

export interface AbandonSoloImageRebuildSessionResponse {
  state: ImageRebuildSessionState;
}

export interface SoloImageRebuildResultResponse {
  sessionId: string;
  finished: boolean;
  result?: ImageRebuildSessionState["result"];
  finishReason?: ImageRebuildSessionState["finishReason"];
  metrics: ImageRebuildSessionState["metrics"];
}

export interface CreateSoloLineBreakerSessionRequest {
  seed?: string;
}

export interface SoloLineBreakerSessionResponse {
  state: LineBreakerSessionState;
}

export interface SubmitSoloLineBreakerPlacementRequest {
  anchorX: number;
  anchorY: number;
  rotation: number;
}

export interface SubmitSoloLineBreakerPlacementResponse
  extends LineBreakerPlacementAttemptResponse {}

export interface AbandonSoloLineBreakerSessionResponse {
  state: LineBreakerSessionState;
}

export interface SoloLineBreakerResultResponse {
  sessionId: string;
  finished: boolean;
  result?: LineBreakerSessionState["result"];
  finishReason?: LineBreakerSessionState["finishReason"];
  metrics: LineBreakerSessionState["metrics"];
  lineClearStats: LineBreakerSessionState["lineClearStats"];
}
