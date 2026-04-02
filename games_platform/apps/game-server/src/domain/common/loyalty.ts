import {
  GameMode,
  GameType,
  HistoryOutcome,
  LoyaltyEntryType
} from "@games-platform/game-contracts";

export interface LoyaltyPolicyConfig {
  defaultExpirationDays?: number;
  multiplier?: number;
}

export interface LoyaltyRewardInput {
  gameType: GameType;
  mode: GameMode;
  outcome: HistoryOutcome;
  score: number;
  finishReason?: string;
  policyConfig?: LoyaltyPolicyConfig;
  imageMetrics?: {
    matchedCells?: number;
    accuracyRatio?: number;
    coverageRatio?: number;
  };
  lineMetrics?: {
    totalLinesCleared?: number;
    maxCombo?: number;
  };
}

export interface LoyaltyRewardDecision {
  formulaVersion: "loyalty_v1";
  entryType: LoyaltyEntryType;
  points: number;
  expiresAt: string;
  reason: string;
  breakdown: {
    participationPoints: number;
    performancePoints: number;
    outcomePoints: number;
    gameBonusPoints: number;
    adjustmentPoints: number;
    minimumApplied: number;
  };
}

/**
 * Compute the expiration date for loyalty points based on the policy.
 * Default: points expire after 30 days. Can be overridden by policy config.
 */
export function computeExpirationDate(
  policyConfig?: { defaultExpirationDays?: number }
): string {
  const days = policyConfig?.defaultExpirationDays ?? 30;
  const expiresAt = new Date();
  expiresAt.setDate(expiresAt.getDate() + days);
  return expiresAt.toISOString();
}

function clamp(value: number, minimum: number, maximum: number): number {
  return Math.max(minimum, Math.min(maximum, value));
}

function getDuplicateOutcomePoints(outcome: HistoryOutcome): number {
  switch (outcome) {
    case "win":
      return 18;
    case "draw":
      return 12;
    case "loss":
      return 8;
    case "forfeit":
      return 2;
    default:
      return 0;
  }
}

function getImageBonus(input: LoyaltyRewardInput): number {
  if (!input.imageMetrics) {
    return 0;
  }

  if (input.mode === GameMode.Solo) {
    return clamp(
      Math.floor((input.imageMetrics.matchedCells ?? 0) / 8) +
        Math.floor((input.imageMetrics.accuracyRatio ?? 0) * 6),
      0,
      20
    );
  }

  return clamp(Math.floor((input.imageMetrics.accuracyRatio ?? 0) * 10), 0, 10);
}

function getLineBonus(input: LoyaltyRewardInput): number {
  if (!input.lineMetrics) {
    return 0;
  }

  const totalLines = input.lineMetrics.totalLinesCleared ?? 0;
  const maxCombo = input.lineMetrics.maxCombo ?? 0;

  if (input.mode === GameMode.Solo) {
    return clamp(totalLines * 2 + Math.floor(maxCombo / 2), 0, 20);
  }

  return clamp(totalLines + Math.floor(maxCombo / 2), 0, 10);
}

export function calculateLoyaltyReward(
  input: LoyaltyRewardInput
): LoyaltyRewardDecision {
  const participationPoints = input.mode === GameMode.Solo ? 10 : 12;
  const performancePoints =
    input.mode === GameMode.Solo
      ? clamp(Math.floor(input.score / 80), 0, 30)
      : clamp(Math.floor(input.score / 100), 0, 20);
  const outcomePoints =
    input.mode === GameMode.Duplicate2P ? getDuplicateOutcomePoints(input.outcome) : 0;
  const gameBonusPoints =
    input.gameType === GameType.ImageRebuild ? getImageBonus(input) : getLineBonus(input);
  const adjustmentPoints =
    input.mode === GameMode.Solo && input.finishReason === "abandoned" ? -4 : 0;
  const minimumApplied =
    input.mode === GameMode.Duplicate2P
      ? input.outcome === "forfeit"
        ? 2
        : 8
      : 6;
  const policyMultiplier = input.policyConfig?.multiplier ?? 1;
  const rawPoints = participationPoints +
    performancePoints +
    outcomePoints +
    gameBonusPoints +
    adjustmentPoints;
  const points = Math.max(
    minimumApplied,
    Math.round(rawPoints * policyMultiplier)
  );

  return {
    formulaVersion: "loyalty_v1",
    entryType:
      input.mode === GameMode.Duplicate2P
        ? LoyaltyEntryType.DuplicateBonus
        : LoyaltyEntryType.SessionReward,
    points,
    expiresAt: computeExpirationDate(input.policyConfig),
    reason:
      input.mode === GameMode.Solo
        ? `${input.gameType}_solo_completion`
        : `${input.gameType}_duplicate_${input.outcome}`,
    breakdown: {
      participationPoints,
      performancePoints,
      outcomePoints,
      gameBonusPoints,
      adjustmentPoints,
      minimumApplied
    }
  };
}
