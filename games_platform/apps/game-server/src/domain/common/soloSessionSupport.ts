import { GameSessionSummary, TurnState } from "@games-platform/game-contracts";
import { randomUUID } from "node:crypto";
import { GameSessionDocument } from "../../storage/mongoose/models/GameSessionModel.js";

type SessionSummarySource = Pick<
  GameSessionDocument,
  "sessionId" | "mode" | "gameType" | "status" | "roomCode" | "hostPlayerId" | "createdAt" | "startedAt" | "endedAt"
>;

export function toGameSessionSummary(source: SessionSummarySource): GameSessionSummary {
  return {
    sessionId: source.sessionId,
    mode: source.mode,
    gameType: source.gameType,
    status: source.status,
    roomCode: source.roomCode,
    hostPlayerId: source.hostPlayerId,
    createdAt: source.createdAt.toISOString(),
    startedAt: source.startedAt?.toISOString(),
    endedAt: source.endedAt?.toISOString()
  };
}

export function resolveSessionSeed(requestedSeed?: string): string {
  const candidate = requestedSeed?.trim();
  return candidate && candidate.length > 0 ? candidate : randomUUID();
}

export function createTimedTurnState(
  turnIndex: number,
  limitMs: number,
  startedAtMs: number,
  input?: {
    skippedOffers?: number;
    expiredTurns?: number;
  }
): TurnState {
  return {
    index: turnIndex,
    startedAt: new Date(startedAtMs).toISOString(),
    deadlineAt: new Date(startedAtMs + limitMs).toISOString(),
    limitMs,
    skippedOffers: input?.skippedOffers ?? 0,
    expiredTurns: input?.expiredTurns ?? 0
  };
}

export function computeRemainingTurnMs(
  turn: Pick<TurnState, "deadlineAt">,
  referenceDate: Date
): number {
  if (!turn.deadlineAt) {
    return 0;
  }

  return Math.max(0, Date.parse(turn.deadlineAt) - referenceDate.getTime());
}

export function calculateTurnTimeBonus(input: {
  turn: Pick<TurnState, "deadlineAt">;
  turnTimeLimitMs: number;
  referenceDate: Date;
  maxBonus: number;
}): number {
  if (!input.turn.deadlineAt || input.turnTimeLimitMs <= 0 || input.maxBonus <= 0) {
    return 0;
  }

  const remainingMs = computeRemainingTurnMs(input.turn, input.referenceDate);
  const ratio = Math.min(1, Math.max(0, remainingMs / input.turnTimeLimitMs));
  return Math.round(ratio * input.maxBonus);
}
