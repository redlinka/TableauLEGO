import {
  AbandonSoloImageRebuildSessionResponse,
  AbandonSoloLineBreakerSessionResponse,
  BootstrapResponse,
  CreateSoloImageRebuildSessionRequest,
  CreateSoloLineBreakerSessionRequest,
  GuestAuthRequest,
  GuestAuthResponse,
  HealthResponse,
  HistoryDetailResponse,
  HistoryResponse,
  LoyaltyBalanceResponse,
  LoyaltyLedgerResponse,
  PhpExchangeResponse,
  PuzzleDetailResponse,
  PuzzleListResponse,
  SoloImageRebuildResultResponse,
  SoloImageRebuildSessionResponse,
  SoloLineBreakerResultResponse,
  SoloLineBreakerSessionResponse,
  SubmitSoloImageRebuildPlacementRequest,
  SubmitSoloImageRebuildPlacementResponse,
  SubmitSoloLineBreakerPlacementRequest,
  SubmitSoloLineBreakerPlacementResponse
} from "@games-platform/game-contracts";
import { requestJson } from "./client";

export function fetchHealth(): Promise<HealthResponse> {
  return requestJson<HealthResponse>("/health");
}

export function fetchBootstrap(token?: string): Promise<BootstrapResponse> {
  return requestJson<BootstrapResponse>("/api/v1/bootstrap", undefined, token);
}

export function fetchPuzzles(): Promise<PuzzleListResponse> {
  return requestJson<PuzzleListResponse>("/api/v1/puzzles");
}

export function fetchPuzzleDetail(puzzleId: string): Promise<PuzzleDetailResponse> {
  return requestJson<PuzzleDetailResponse>(`/api/v1/puzzles/${encodeURIComponent(puzzleId)}`);
}

export function authenticateGuest(
  payload: GuestAuthRequest
): Promise<GuestAuthResponse> {
  return requestJson<GuestAuthResponse>("/api/v1/auth/guest", {
    method: "POST",
    body: JSON.stringify(payload)
  });
}

export function exchangePhpToken(phpToken: string): Promise<PhpExchangeResponse> {
  return requestJson<PhpExchangeResponse>("/api/v1/auth/php/exchange", {
    method: "POST",
    body: JSON.stringify({ phpToken })
  });
}

export function fetchHistory(token?: string): Promise<HistoryResponse> {
  return requestJson<HistoryResponse>("/api/v1/history", undefined, token);
}

export function fetchHistoryDetail(
  historyId: string,
  token: string
): Promise<HistoryDetailResponse> {
  return requestJson<HistoryDetailResponse>(
    `/api/v1/history/${encodeURIComponent(historyId)}`,
    undefined,
    token
  );
}

export function fetchLoyaltyBalance(token: string): Promise<LoyaltyBalanceResponse> {
  return requestJson<LoyaltyBalanceResponse>("/api/v1/loyalty/balance", undefined, token);
}

export function fetchLoyaltyLedger(
  token: string,
  limit = 20
): Promise<LoyaltyLedgerResponse> {
  return requestJson<LoyaltyLedgerResponse>(
    `/api/v1/loyalty/ledger?limit=${encodeURIComponent(limit.toString())}`,
    undefined,
    token
  );
}

export function createSoloImageRebuildSession(
  payload: CreateSoloImageRebuildSessionRequest,
  token: string
): Promise<SoloImageRebuildSessionResponse> {
  return requestJson<SoloImageRebuildSessionResponse>(
    "/api/v1/solo/image-rebuild/sessions",
    {
      method: "POST",
      body: JSON.stringify(payload)
    },
    token
  );
}

export function fetchSoloImageRebuildSession(
  sessionId: string,
  token: string
): Promise<SoloImageRebuildSessionResponse> {
  return requestJson<SoloImageRebuildSessionResponse>(
    `/api/v1/solo/image-rebuild/sessions/${encodeURIComponent(sessionId)}`,
    undefined,
    token
  );
}

export function submitSoloImageRebuildPlacement(
  sessionId: string,
  payload: SubmitSoloImageRebuildPlacementRequest,
  token: string
): Promise<SubmitSoloImageRebuildPlacementResponse> {
  return requestJson<SubmitSoloImageRebuildPlacementResponse>(
    `/api/v1/solo/image-rebuild/sessions/${encodeURIComponent(sessionId)}/placements`,
    {
      method: "POST",
      body: JSON.stringify(payload)
    },
    token
  );
}

export function abandonSoloImageRebuildSession(
  sessionId: string,
  token: string
): Promise<AbandonSoloImageRebuildSessionResponse> {
  return requestJson<AbandonSoloImageRebuildSessionResponse>(
    `/api/v1/solo/image-rebuild/sessions/${encodeURIComponent(sessionId)}/abandon`,
    {
      method: "POST"
    },
    token
  );
}

export function fetchSoloImageRebuildResult(
  sessionId: string,
  token: string
): Promise<SoloImageRebuildResultResponse> {
  return requestJson<SoloImageRebuildResultResponse>(
    `/api/v1/solo/image-rebuild/sessions/${encodeURIComponent(sessionId)}/result`,
    undefined,
    token
  );
}

export function createSoloLineBreakerSession(
  payload: CreateSoloLineBreakerSessionRequest,
  token: string
): Promise<SoloLineBreakerSessionResponse> {
  return requestJson<SoloLineBreakerSessionResponse>(
    "/api/v1/solo/line-breaker/sessions",
    {
      method: "POST",
      body: JSON.stringify(payload)
    },
    token
  );
}

export function fetchSoloLineBreakerSession(
  sessionId: string,
  token: string
): Promise<SoloLineBreakerSessionResponse> {
  return requestJson<SoloLineBreakerSessionResponse>(
    `/api/v1/solo/line-breaker/sessions/${encodeURIComponent(sessionId)}`,
    undefined,
    token
  );
}

export function submitSoloLineBreakerPlacement(
  sessionId: string,
  payload: SubmitSoloLineBreakerPlacementRequest,
  token: string
): Promise<SubmitSoloLineBreakerPlacementResponse> {
  return requestJson<SubmitSoloLineBreakerPlacementResponse>(
    `/api/v1/solo/line-breaker/sessions/${encodeURIComponent(sessionId)}/placements`,
    {
      method: "POST",
      body: JSON.stringify(payload)
    },
    token
  );
}

export function abandonSoloLineBreakerSession(
  sessionId: string,
  token: string
): Promise<AbandonSoloLineBreakerSessionResponse> {
  return requestJson<AbandonSoloLineBreakerSessionResponse>(
    `/api/v1/solo/line-breaker/sessions/${encodeURIComponent(sessionId)}/abandon`,
    {
      method: "POST"
    },
    token
  );
}

export function fetchSoloLineBreakerResult(
  sessionId: string,
  token: string
): Promise<SoloLineBreakerResultResponse> {
  return requestJson<SoloLineBreakerResultResponse>(
    `/api/v1/solo/line-breaker/sessions/${encodeURIComponent(sessionId)}/result`,
    undefined,
    token
  );
}
