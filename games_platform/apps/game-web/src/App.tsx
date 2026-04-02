import {
  BoardState,
  BootstrapResponse,
  BrickRotationDefinition,
  BrickShapeDefinition,
  ChatMessageView,
  DuplicateMatchResult,
  DuplicateParticipantResult,
  DuplicateRoomState,
  GameMode,
  GameType,
  GuestAuthResponse,
  HealthResponse,
  HistoryDetailResponse,
  HistoryResponse,
  ImageRebuildMetrics,
  ImageRebuildSessionState,
  LineBreakerLineClearStats,
  LineBreakerMetrics,
  LineBreakerSessionState,
  LoyaltyBalanceResponse,
  LoyaltyLedgerResponse,
  PuzzleDetailResponse,
  PuzzleListResponse,
  ScoreResult,
  ServerWsMessage,
  SoloImageRebuildResultResponse,
  SoloLineBreakerResultResponse
} from "@games-platform/game-contracts";
import { FormEvent, ReactNode, useEffect, useRef, useState } from "react";
import {
  abandonSoloImageRebuildSession,
  abandonSoloLineBreakerSession,
  authenticateGuest,
  createSoloImageRebuildSession,
  createSoloLineBreakerSession,
  exchangePhpToken,
  fetchBootstrap,
  fetchHealth,
  fetchHistory,
  fetchHistoryDetail,
  fetchLoyaltyBalance,
  fetchLoyaltyLedger,
  fetchPuzzleDetail,
  fetchPuzzles,
  fetchSoloImageRebuildResult,
  fetchSoloImageRebuildSession,
  fetchSoloLineBreakerResult,
  fetchSoloLineBreakerSession,
  submitSoloImageRebuildPlacement,
  submitSoloLineBreakerPlacement
} from "./api/gameApi";
import { resolveBoardColor } from "./colorUtils";
import { GAME_CONTENT, MODE_CONTENT, POINTS_EXPLANATION } from "./gameContent";
import { GameWebSocketClient } from "./ws/websocketClient";

const GUEST_STORAGE_KEY = "tableaulego_games_guest";
const RESUME_STORAGE_KEY = "tableaulego_games_resume";
const DEBUG_AVAILABLE = import.meta.env.VITE_DEBUG_UI === "true";
const PHP_SHOP_URL = import.meta.env.VITE_PHP_SHOP_URL || "http://127.0.0.1:8080";

type AsyncState<T> = {
  loading: boolean;
  data?: T;
  error?: string;
};

type GridCoord = {
  x: number;
  y: number;
};

type RealtimeStatus =
  | "idle"
  | "connecting"
  | "authenticated"
  | "reconnecting"
  | "closed";

type ResumeState = {
  sessionId: string;
  roomCode: string;
  resumeToken: string;
};

type Highlight = {
  label: string;
  value: string;
};

function loadStoredJson<T>(key: string): T | undefined {
  if (typeof window === "undefined") {
    return undefined;
  }

  const raw = window.localStorage.getItem(key);

  if (!raw) {
    return undefined;
  }

  try {
    return JSON.parse(raw) as T;
  } catch {
    window.localStorage.removeItem(key);
    return undefined;
  }
}

function storeJson(key: string, value: unknown): void {
  if (typeof window === "undefined") {
    return;
  }

  window.localStorage.setItem(key, JSON.stringify(value));
}

function clearStoredValue(key: string): void {
  if (typeof window === "undefined") {
    return;
  }

  window.localStorage.removeItem(key);
}

function formatJson(value: unknown): string {
  return JSON.stringify(value, null, 2);
}

function formatDateTime(value: string | undefined): string {
  if (!value) {
    return "-";
  }

  return new Intl.DateTimeFormat("fr-FR", {
    dateStyle: "short",
    timeStyle: "short"
  }).format(new Date(value));
}

function formatDuration(durationMs: number | undefined): string {
  if (!durationMs || durationMs < 1_000) {
    return "0 s";
  }

  const totalSeconds = Math.floor(durationMs / 1_000);
  const minutes = Math.floor(totalSeconds / 60);
  const seconds = totalSeconds % 60;

  if (!minutes) {
    return `${seconds} s`;
  }

  return `${minutes} min ${seconds.toString().padStart(2, "0")} s`;
}

function formatPercent(value: number | undefined): string {
  return `${Math.round((value ?? 0) * 100)} %`;
}

function formatRemainingSeconds(deadlineAt: string | undefined, nowMs: number): string {
  if (!deadlineAt) {
    return "0";
  }

  return Math.max(0, Math.ceil((Date.parse(deadlineAt) - nowMs) / 1_000)).toString();
}

function loadStoredResumeState(): ResumeState | null {
  return loadStoredJson<ResumeState>(RESUME_STORAGE_KEY) ?? null;
}

function getRotationDefinition(
  shape: BrickShapeDefinition | undefined,
  rotation: number
): BrickRotationDefinition | undefined {
  return shape?.rotations.find((entry) => entry.rotation === rotation);
}

function getGhostCells(
  shape: BrickShapeDefinition | undefined,
  rotation: number,
  anchor: GridCoord | null
): Set<string> {
  if (!shape || !anchor) {
    return new Set();
  }

  const selected = getRotationDefinition(shape, rotation);

  if (!selected) {
    return new Set();
  }

  return new Set(selected.cells.map((cell) => `${anchor.x + cell.x}:${anchor.y + cell.y}`));
}

function appendChat(state: DuplicateRoomState | undefined, message: ChatMessageView) {
  if (!state || state.session.sessionId !== message.sessionId) {
    return state;
  }

  return {
    ...state,
    chatMessages: [...state.chatMessages.filter((entry) => entry.messageId !== message.messageId), message]
      .sort((left, right) => Date.parse(left.createdAt) - Date.parse(right.createdAt))
      .slice(-20)
  };
}

function toBoardState(
  puzzle: PuzzleDetailResponse["item"] | undefined
): BoardState | undefined {
  if (!puzzle) {
    return undefined;
  }

  return {
    width: puzzle.gridWidth,
    height: puzzle.gridHeight,
    occupiedCount: puzzle.gridWidth * puzzle.gridHeight,
    cells: puzzle.cells.map((row) => [...row])
  };
}

function toFriendlyPlacementError(reason: string | undefined): string {
  switch (reason) {
    case "out_of_bounds":
      return "La brique depasse du plateau.";
    case "collision":
      return "Cette zone est deja occupee.";
    case "offer_mismatch":
      return "La brique a change. Reessaie avec l'offre courante.";
    case "session_finished":
    case "player_finished":
      return "La partie est deja terminee.";
    case "session_not_active":
      return "La partie n'a pas encore commence.";
    default:
      return "Ce placement n'est pas valide.";
  }
}

function toFriendlySocketError(message: string): string {
  if (message.includes("AUTH_REQUIRED")) {
    return "Connecte un joueur avant d'utiliser le mode duplicate.";
  }

  if (message.includes("ROOM_NOT_FOUND")) {
    return "Aucun salon ne correspond a ce code.";
  }

  if (message.includes("ROOM_FULL")) {
    return "Ce salon est deja complet.";
  }

  return message;
}

function toConnectionLabel(value: string | undefined): string {
  switch (value) {
    case "connected":
      return "Connecte";
    case "disconnected":
      return "Deconnecte";
    case "reconnecting":
      return "Reconnexion";
    default:
      return "Inconnu";
  }
}

function toRealtimeStatusLabel(value: RealtimeStatus): string {
  switch (value) {
    case "connecting":
      return "Connexion en cours";
    case "authenticated":
      return "Connecte";
    case "reconnecting":
      return "Connexion perdue, reprise en cours";
    case "closed":
      return "Connexion interrompue";
    default:
      return "En attente de connexion";
  }
}

function toModeIconLabel(value: GameMode): string {
  switch (value) {
    case GameMode.Duplicate2P:
      return "2P";
    default:
      return "SO";
  }
}

function toContextualHint(
  gameType: GameType,
  mode: GameMode,
  hasTargetBoard: boolean
): string {
  if (gameType === GameType.ImageRebuild) {
    return hasTargetBoard
      ? "Observe l'image cible, choisis une rotation puis place la brique au bon endroit."
      : "Choisis une rotation puis place la brique sur le plateau.";
  }

  return mode === GameMode.Duplicate2P
    ? "Choisis une rotation puis place la brique pour completer des lignes d'une meme couleur avant ton adversaire."
    : "Choisis une rotation puis place la brique pour completer des lignes d'une meme couleur.";
}

function toTurnStatusLabel(value: string | undefined): string {
  switch (value) {
    case "pending":
      return "En attente de ton placement";
    case "locked":
      return "Placement envoye";
    case "auto_placed":
      return "Place automatiquement";
    case "skipped":
      return "Tour saute";
    case "finished":
      return "Partie terminee";
    default:
      return "Tour en cours";
  }
}

function toOutcomeLabel(value: string | undefined): string {
  switch (value) {
    case "win":
      return "Victoire";
    case "loss":
      return "Defaite";
    case "draw":
      return "Match nul";
    case "forfeit":
      return "Forfait";
    case "solo":
      return "Partie terminee";
    case "abandoned":
      return "Abandon";
    default:
      return "En cours";
  }
}

function toFinishReasonLabel(value: string | undefined): string {
  switch (value) {
    case "completed":
      return "Tous les tours ont ete joues.";
    case "sequence_exhausted":
      return "La sequence de briques est terminee.";
    case "no_more_moves":
      return "Plus aucun placement n'etait possible.";
    case "abandoned":
      return "La partie a ete abandonnee.";
    case "forfeit":
      return "La partie s'est terminee sur forfait.";
    case "cancelled":
      return "Le salon a ete annule.";
    case "left":
      return "Le joueur a quitte la partie.";
    default:
      return "La partie suit son cours.";
  }
}

function toDuplicatePhaseLabel(value: string | undefined): string {
  switch (value) {
    case "waiting":
      return "En attente d'un deuxieme joueur";
    case "ready":
      return "Pret a demarrer";
    case "in_game":
      return "Partie en cours";
    case "finished":
      return "Partie terminee";
    case "cancelled":
      return "Salon annule";
    default:
      return "Configuration";
  }
}

function toBoardHighlights(
  gameType: GameType,
  score: ScoreResult | undefined,
  imageMetrics: ImageRebuildMetrics | undefined,
  lineMetrics: LineBreakerMetrics | undefined,
  lineStats: LineBreakerLineClearStats | undefined
): Highlight[] {
  if (gameType === GameType.ImageRebuild) {
    return [
      { label: "Score actuel", value: String(score?.score ?? 0) },
      { label: "Precision", value: formatPercent(imageMetrics?.accuracyRatio) },
      {
        label: "Cases justes",
        value: `${imageMetrics?.matchedCells ?? 0} / ${imageMetrics?.totalCells ?? 100}`
      },
      { label: "Couverture", value: formatPercent(imageMetrics?.coverageRatio) }
    ];
  }

  return [
    { label: "Score actuel", value: String(score?.score ?? 0) },
    { label: "Lignes effacees", value: String(lineStats?.totalLinesCleared ?? 0) },
    { label: "Combo max", value: String(lineStats?.maxCombo ?? 0) },
    { label: "Dernier clear", value: String(lineMetrics?.lastClearedCellCount ?? 0) }
  ];
}

function toHistoryLabel(entry: HistoryResponse["items"][number]): string {
  const gameLabel =
    entry.gameType === GameType.ImageRebuild
      ? GAME_CONTENT[GameType.ImageRebuild].title
      : GAME_CONTENT[GameType.LineBreaker].title;
  const modeLabel = entry.mode === GameMode.Solo ? "Solo" : "Duplicate";

  return `${gameLabel} | ${modeLabel}`;
}

function toHistoryHighlights(detail: HistoryDetailResponse | undefined): Highlight[] {
  if (!detail) {
    return [];
  }

  const metadata = (detail.item.metadata ?? {}) as Record<string, unknown>;
  const gameSpecific =
    typeof metadata.gameSpecific === "object" && metadata.gameSpecific
      ? (metadata.gameSpecific as Record<string, unknown>)
      : metadata;
  const metrics =
    typeof gameSpecific.metrics === "object" && gameSpecific.metrics
      ? (gameSpecific.metrics as Record<string, unknown>)
      : undefined;
  const lineStats =
    typeof gameSpecific.lineClearStats === "object" && gameSpecific.lineClearStats
      ? (gameSpecific.lineClearStats as Record<string, unknown>)
      : undefined;
  const highlights: Highlight[] = [
    { label: "Resultat", value: toOutcomeLabel(detail.item.outcome) },
    { label: "Fin de partie", value: toFinishReasonLabel(detail.item.finishReason) }
  ];

  if (detail.item.gameType === GameType.ImageRebuild && metrics) {
    highlights.push(
      { label: "Cases justes", value: String(metrics.matchedCells ?? 0) },
      { label: "Precision", value: formatPercent(Number(metrics.accuracyRatio ?? 0)) }
    );
  }

  if (detail.item.gameType === GameType.LineBreaker) {
    highlights.push(
      { label: "Lignes effacees", value: String(lineStats?.totalLinesCleared ?? 0) },
      { label: "Combo max", value: String(lineStats?.maxCombo ?? 0) }
    );
  }

  return highlights.slice(0, 4);
}

function getDuplicateResultForPlayer(
  result: DuplicateMatchResult | undefined,
  playerId: string | undefined
): DuplicateParticipantResult | undefined {
  return result?.players.find((entry) => entry.playerId === playerId);
}

export default function App() {
  const [selectedGameType, setSelectedGameType] = useState<GameType>(GameType.ImageRebuild);
  const [selectedMode, setSelectedMode] = useState<GameMode>(GameMode.Solo);
  const [selectedPuzzleId, setSelectedPuzzleId] = useState("");
  const [selectedRotation, setSelectedRotation] = useState<0 | 90 | 180 | 270>(0);
  const [hoverCell, setHoverCell] = useState<GridCoord | null>(null);
  const [roomCodeInput, setRoomCodeInput] = useState("");
  const [chatInput, setChatInput] = useState("");
  const [message, setMessage] = useState("");
  const [nowMs, setNowMs] = useState(Date.now());
  const [socketStatus, setSocketStatus] = useState<RealtimeStatus>("idle");
  const [socketError, setSocketError] = useState("");
  const [resumeState, setResumeState] = useState<ResumeState | null>(loadStoredResumeState);
  const [debugOpen, setDebugOpen] = useState(false);

  const [healthState, setHealthState] = useState<AsyncState<HealthResponse>>({ loading: true });
  const [bootstrapState, setBootstrapState] = useState<AsyncState<BootstrapResponse>>({ loading: true });
  const [puzzlesState, setPuzzlesState] = useState<AsyncState<PuzzleListResponse>>({ loading: true });
  const [puzzleState, setPuzzleState] = useState<AsyncState<PuzzleDetailResponse>>({ loading: false });
  const [guestState, setGuestState] = useState<AsyncState<GuestAuthResponse>>({
    loading: false,
    data: loadStoredJson<GuestAuthResponse>(GUEST_STORAGE_KEY)
  });
  const [historyState, setHistoryState] = useState<AsyncState<HistoryResponse>>({ loading: false });
  const [historyDetailState, setHistoryDetailState] = useState<AsyncState<HistoryDetailResponse>>({ loading: false });
  const [loyaltyBalanceState, setLoyaltyBalanceState] = useState<AsyncState<LoyaltyBalanceResponse>>({ loading: false });
  const [loyaltyLedgerState, setLoyaltyLedgerState] = useState<AsyncState<LoyaltyLedgerResponse>>({ loading: false });
  const [imageState, setImageState] = useState<AsyncState<ImageRebuildSessionState>>({ loading: false });
  const [imageResultState, setImageResultState] = useState<AsyncState<SoloImageRebuildResultResponse>>({ loading: false });
  const [lineState, setLineState] = useState<AsyncState<LineBreakerSessionState>>({ loading: false });
  const [lineResultState, setLineResultState] = useState<AsyncState<SoloLineBreakerResultResponse>>({ loading: false });
  const [duplicateState, setDuplicateState] = useState<AsyncState<DuplicateRoomState>>({ loading: false });

  const wsRef = useRef(new GameWebSocketClient());
  const manualSocketCloseRef = useRef(false);
  const reconnectTimerRef = useRef<number | null>(null);
  const resumeStateRef = useRef<ResumeState | null>(resumeState);
  const accessTokenRef = useRef<string | undefined>(guestState.data?.accessToken);
  const autoResumeAttemptRef = useRef("");

  const accessToken = guestState.data?.accessToken;
  const selectedGameContent = GAME_CONTENT[selectedGameType];
  const selectedModeContent = MODE_CONTENT[selectedMode];
  const currentPlayerId =
    guestState.data?.player.playerId ?? bootstrapState.data?.auth.currentPlayer?.playerId;
  const shapes = bootstrapState.data?.bricks.shapes ?? [];
  const selectedPuzzle = puzzleState.data?.item;
  const soloSession =
    selectedGameType === GameType.ImageRebuild ? imageState.data : lineState.data;
  const soloResult =
    selectedGameType === GameType.ImageRebuild
      ? imageResultState.data
      : lineResultState.data;
  const duplicateRoom = duplicateState.data;
  const duplicatePlayers = duplicateRoom?.players ?? [];
  const ownPlayer =
    duplicatePlayers.find((player) => player.playerId === currentPlayerId) ??
    duplicatePlayers[0];
  const opponent = duplicatePlayers.find(
    (player) => player.playerId !== ownPlayer?.playerId
  );
  const targetBoard =
    selectedMode === GameMode.Solo
      ? selectedGameType === GameType.ImageRebuild
        ? toBoardState(selectedPuzzle)
        : undefined
      : duplicateRoom?.gameType === GameType.ImageRebuild
        ? duplicateRoom.targetBoard
        : undefined;
  const paletteColors =
    selectedMode === GameMode.Solo
      ? selectedPuzzle?.paletteColors
      : duplicateRoom?.gameType === GameType.ImageRebuild
        ? duplicateRoom.paletteColors
        : undefined;
  const playerBoard =
    selectedMode === GameMode.Solo ? soloSession?.board : ownPlayer?.board;
  const opponentBoard =
    selectedMode === GameMode.Duplicate2P ? opponent?.board : undefined;
  const activeOffer =
    selectedMode === GameMode.Solo
      ? soloSession?.currentOffer
      : duplicateRoom?.currentOffer;
  const activeShape = shapes.find((shape) => shape.shapeId === activeOffer?.shapeId);
  const activeRotation =
    getRotationDefinition(activeShape, selectedRotation) ?? activeShape?.rotations[0];
  const ghostCells = getGhostCells(
    activeShape,
    activeRotation?.rotation ?? 0,
    hoverCell
  );
  const activeHistoryItem = historyDetailState.data?.item;
  const activeSessionId =
    selectedMode === GameMode.Solo
      ? soloSession?.session.sessionId
      : duplicateRoom?.session.sessionId ?? resumeState?.sessionId;
  const currentHistoryEntry = historyState.data?.items.find(
    (entry) => entry.sessionId === activeSessionId
  );
  const currentRewardPoints = currentHistoryEntry?.rewardPoints;
  const currentScore =
    selectedMode === GameMode.Solo ? soloSession?.score : ownPlayer?.result;
  const currentImageMetrics: ImageRebuildMetrics | undefined =
    selectedMode === GameMode.Solo
      ? selectedGameType === GameType.ImageRebuild
        ? imageState.data?.metrics
        : undefined
      : duplicateRoom?.gameType === GameType.ImageRebuild
        ? (ownPlayer?.metrics as ImageRebuildMetrics | undefined)
        : undefined;
  const currentLineMetrics: LineBreakerMetrics | undefined =
    selectedMode === GameMode.Solo
      ? selectedGameType === GameType.LineBreaker
        ? lineState.data?.metrics
        : undefined
      : duplicateRoom?.gameType === GameType.LineBreaker
        ? (ownPlayer?.metrics as LineBreakerMetrics | undefined)
        : undefined;
  const currentLineStats: LineBreakerLineClearStats | undefined =
    selectedMode === GameMode.Solo
      ? selectedGameType === GameType.LineBreaker
        ? lineState.data?.lineClearStats
        : undefined
      : duplicateRoom?.gameType === GameType.LineBreaker
        ? ((ownPlayer && "lineClearStats" in ownPlayer
            ? ownPlayer.lineClearStats
            : undefined) as LineBreakerLineClearStats | undefined)
        : undefined;
  const ownDuplicateResult = getDuplicateResultForPlayer(
    duplicateRoom?.result,
    ownPlayer?.playerId
  );
  const opponentDuplicateResult = getDuplicateResultForPlayer(
    duplicateRoom?.result,
    opponent?.playerId
  );

  function clearReconnectTimer() {
    if (reconnectTimerRef.current !== null) {
      window.clearTimeout(reconnectTimerRef.current);
      reconnectTimerRef.current = null;
    }
  }

  function setDuplicateRoomState(state: DuplicateRoomState) {
    setDuplicateState({ loading: false, data: state });
    setSelectedMode(GameMode.Duplicate2P);
    setSelectedGameType(state.gameType);
  }

  async function loadInitialData(token?: string) {
    setHealthState({ loading: true });
    setBootstrapState({ loading: true });
    setPuzzlesState({ loading: true });

    try {
      const [health, bootstrap, puzzles] = await Promise.all([
        fetchHealth(),
        fetchBootstrap(token),
        fetchPuzzles()
      ]);
      setHealthState({ loading: false, data: health });
      setBootstrapState({ loading: false, data: bootstrap });
      setPuzzlesState({ loading: false, data: puzzles });
    } catch (error) {
      const value = error instanceof Error ? error.message : "Backend indisponible";
      setHealthState({ loading: false, error: value });
      setBootstrapState({ loading: false, error: value });
      setPuzzlesState({ loading: false, error: value });
    }
  }

  async function loadPlayerPanels(token?: string) {
    if (!token) {
      setHistoryState({ loading: false });
      setHistoryDetailState({ loading: false });
      setLoyaltyBalanceState({ loading: false });
      setLoyaltyLedgerState({ loading: false });
      return;
    }

    setHistoryState((current) => ({ loading: true, data: current.data }));
    setLoyaltyBalanceState((current) => ({ loading: true, data: current.data }));
    setLoyaltyLedgerState((current) => ({ loading: true, data: current.data }));

    try {
      const [history, loyaltyBalance, loyaltyLedger] = await Promise.all([
        fetchHistory(token),
        fetchLoyaltyBalance(token),
        fetchLoyaltyLedger(token, 20)
      ]);
      setHistoryState({ loading: false, data: history });
      setLoyaltyBalanceState({ loading: false, data: loyaltyBalance });
      setLoyaltyLedgerState({ loading: false, data: loyaltyLedger });
    } catch (error) {
      const value =
        error instanceof Error
          ? error.message
          : "Impossible de charger l'historique.";
      setHistoryState({ loading: false, error: value });
      setLoyaltyBalanceState({ loading: false, error: value });
      setLoyaltyLedgerState({ loading: false, error: value });
    }
  }

  async function ensureGuestToken() {
    if (guestState.data?.accessToken) {
      return guestState.data.accessToken;
    }

    setGuestState({ loading: true });
    const response = await authenticateGuest({});
    setGuestState({ loading: false, data: response });
    await Promise.all([
      loadInitialData(response.accessToken),
      loadPlayerPanels(response.accessToken)
    ]);
    return response.accessToken;
  }

  async function loadSoloResult(sessionId: string, gameType: GameType, token: string) {
    if (gameType === GameType.ImageRebuild) {
      setImageResultState({ loading: true });
      setImageResultState({
        loading: false,
        data: await fetchSoloImageRebuildResult(sessionId, token)
      });
    } else {
      setLineResultState({ loading: true });
      setLineResultState({
        loading: false,
        data: await fetchSoloLineBreakerResult(sessionId, token)
      });
    }

    await loadPlayerPanels(token);
  }

  async function loadHistoryDetailItem(historyId: string) {
    if (!accessTokenRef.current) {
      return;
    }

    setHistoryDetailState({ loading: true });

    try {
      const detail = await fetchHistoryDetail(historyId, accessTokenRef.current);
      setHistoryDetailState({ loading: false, data: detail });
    } catch (error) {
      setHistoryDetailState({
        loading: false,
        error: error instanceof Error ? error.message : "Detail indisponible"
      });
    }
  }

  function sendResume() {
    const currentResume = resumeStateRef.current;

    if (!currentResume) {
      return;
    }

    wsRef.current.send({
      type: "session.resume",
      requestId: crypto.randomUUID(),
      payload: {
        sessionId: currentResume.sessionId,
        resumeToken: currentResume.resumeToken
      }
    });
  }

  function scheduleReconnect(delayMs = 1_250) {
    if (!resumeStateRef.current) {
      return;
    }

    clearReconnectTimer();
    setSocketStatus("reconnecting");
    setSocketError("Connexion perdue, tentative de reconnexion...");
    reconnectTimerRef.current = window.setTimeout(() => {
      void connectSocket(true);
    }, delayMs);
  }

  async function connectSocket(expectResume = Boolean(resumeStateRef.current)) {
    if (socketStatus === "connecting" || socketStatus === "authenticated") {
      return;
    }

    const token = accessTokenRef.current ?? (await ensureGuestToken());

    manualSocketCloseRef.current = false;
    setSocketStatus(expectResume ? "reconnecting" : "connecting");
    setSocketError("");
    wsRef.current.connect({
      url: bootstrapState.data?.service.websocketUrl,
      token,
      onMessage: handleWsMessage,
      onOpen: () => setSocketStatus(expectResume ? "reconnecting" : "connecting"),
      onClose: () => {
        const manualClose = manualSocketCloseRef.current;

        manualSocketCloseRef.current = false;
        setSocketStatus("closed");

        if (!manualClose && resumeStateRef.current) {
          scheduleReconnect();
        }
      },
      onError: () => {
        setSocketStatus("closed");
        setSocketError("Connexion temps reel indisponible.");
      }
    });
  }

  function disconnectSocket(manual = true) {
    if (manual) {
      manualSocketCloseRef.current = true;
    }

    clearReconnectTimer();
    wsRef.current.disconnect();
  }

  function handleWsMessage(event: ServerWsMessage) {
    switch (event.type) {
      case "session.authenticated":
        setSocketStatus("authenticated");
        setSocketError("");
        if (resumeStateRef.current) {
          sendResume();
        }
        return;
      case "session.resumed":
        clearReconnectTimer();
        setResumeState({
          sessionId: event.payload.session.sessionId,
          roomCode: event.payload.state.roomCode,
          resumeToken: event.payload.resumeToken
        });
        setDuplicateRoomState(event.payload.state);
        setMessage("Partie duplicate reprise.");
        return;
      case "room.created":
      case "room.joined":
        clearReconnectTimer();
        setResumeState({
          sessionId: event.payload.sessionId,
          roomCode: event.payload.roomCode,
          resumeToken: event.payload.resumeToken
        });
        setRoomCodeInput(event.payload.roomCode);
        setDuplicateRoomState(event.payload.state);
        setMessage(
          event.type === "room.created"
            ? `Salon cree : ${event.payload.roomCode}`
            : `Salon rejoint : ${event.payload.roomCode}`
        );
        return;
      case "room.state":
      case "game.state":
      case "game.started":
      case "game.turn_started":
      case "game.turn_resolved":
      case "game.finished":
        setDuplicateRoomState(event.payload.state);
        if (event.type === "game.turn_resolved") {
          setMessage(`Tour ${event.payload.resolution.turnIndex} resolu.`);
        }
        if (event.type === "game.finished") {
          setMessage("Partie duplicate terminee.");
          void loadPlayerPanels(accessTokenRef.current);
        }
        return;
      case "room.player_joined":
        setDuplicateRoomState(event.payload.state);
        setMessage("Le deuxieme joueur a rejoint le salon.");
        return;
      case "room.player_ready_changed":
        setDuplicateRoomState(event.payload.state);
        return;
      case "game.player_disconnected":
        setDuplicateRoomState(event.payload.state);
        setMessage("Connexion adverse perdue. Le tour continue cote serveur.");
        return;
      case "game.player_reconnected":
        setDuplicateRoomState(event.payload.state);
        setMessage("Le joueur deconnecte a repris la partie.");
        return;
      case "room.chat_message":
        setDuplicateState((current) => ({
          loading: false,
          data: appendChat(current.data, event.payload.message)
        }));
        return;
      case "game.action_rejected":
        setMessage(toFriendlyPlacementError(event.payload.reason));
        return;
      case "error":
        setSocketError(toFriendlySocketError(`${event.payload.code}: ${event.payload.message}`));
        return;
      case "room.player_left":
      case "pong":
        return;
    }
  }

  useEffect(() => {
    resumeStateRef.current = resumeState;
    if (resumeState) {
      storeJson(RESUME_STORAGE_KEY, resumeState);
      return;
    }

    clearStoredValue(RESUME_STORAGE_KEY);
  }, [resumeState]);

  useEffect(() => {
    accessTokenRef.current = guestState.data?.accessToken;
    if (guestState.data) {
      storeJson(GUEST_STORAGE_KEY, guestState.data);
      return;
    }

    clearStoredValue(GUEST_STORAGE_KEY);
  }, [guestState.data]);

  useEffect(() => {
    const hash = window.location.hash.replace(/^#/, "");
    const hashParams = new URLSearchParams(hash);
    const phpToken = hashParams.get("phpToken") || new URLSearchParams(window.location.search).get("phpToken");
    const urlRoomCode = hashParams.get("roomCode") || new URLSearchParams(window.location.search).get("roomCode");

    if (urlRoomCode) {
      setRoomCodeInput(urlRoomCode.trim().toUpperCase());
      setSelectedMode(GameMode.Duplicate2P);
      setMessage(`Code de salon detecte : ${urlRoomCode.trim().toUpperCase()}. Connecte-toi puis rejoins le salon.`);
      window.history.replaceState({}, "", window.location.pathname);
    }

    if (phpToken) {
      window.history.replaceState({}, "", window.location.pathname);
      exchangePhpToken(phpToken)
        .then((response) => {
          const guestData = {
            accessToken: response.accessToken,
            tokenType: response.tokenType,
            expiresAt: response.expiresAt,
            player: response.player
          };
          setGuestState({ loading: false, data: guestData });
          void loadInitialData(response.accessToken);
          void loadPlayerPanels(response.accessToken);
        })
        .catch(() => {
          const token = guestState.data?.accessToken;
          void loadInitialData(token);
          void loadPlayerPanels(token);
        });
    } else {
      const token = guestState.data?.accessToken;
      void loadInitialData(token);
      void loadPlayerPanels(token);
    }

    const timer = window.setInterval(() => setNowMs(Date.now()), 1_000);

    return () => {
      window.clearInterval(timer);
      disconnectSocket();
    };
  }, []);

  useEffect(() => {
    if (!selectedPuzzleId && puzzlesState.data?.items.length) {
      setSelectedPuzzleId(puzzlesState.data.items[0].id);
    }
  }, [puzzlesState.data, selectedPuzzleId]);

  useEffect(() => {
    if (!selectedPuzzleId || selectedGameType !== GameType.ImageRebuild) {
      return;
    }

    setPuzzleState({ loading: true });
    fetchPuzzleDetail(selectedPuzzleId)
      .then((response) => setPuzzleState({ loading: false, data: response }))
      .catch((error) =>
        setPuzzleState({
          loading: false,
          error: error instanceof Error ? error.message : "Puzzle indisponible"
        })
      );
  }, [selectedPuzzleId, selectedGameType]);

  useEffect(() => {
    const fallbackRotation = activeShape?.rotations[0]?.rotation;

    if (
      activeShape &&
      !activeShape.rotations.some((rotation) => rotation.rotation === selectedRotation) &&
      fallbackRotation !== undefined
    ) {
      setSelectedRotation(fallbackRotation);
    }
  }, [activeShape, selectedRotation]);

  useEffect(() => {
    function handleKeyDown(event: KeyboardEvent) {
      if (
        event.target instanceof HTMLInputElement ||
        event.target instanceof HTMLTextAreaElement
      ) {
        return;
      }

      const board = playerBoard;

      if (!board || !activeOffer) {
        return;
      }

      switch (event.key) {
        case "ArrowUp": {
          event.preventDefault();
          setHoverCell((prev) => {
            const current = prev ?? { x: 0, y: 0 };
            return { x: current.x, y: Math.max(0, current.y - 1) };
          });
          break;
        }
        case "ArrowDown": {
          event.preventDefault();
          setHoverCell((prev) => {
            const current = prev ?? { x: 0, y: 0 };
            return { x: current.x, y: Math.min(board.height - 1, current.y + 1) };
          });
          break;
        }
        case "ArrowLeft": {
          event.preventDefault();
          setHoverCell((prev) => {
            const current = prev ?? { x: 0, y: 0 };
            return { x: Math.max(0, current.x - 1), y: current.y };
          });
          break;
        }
        case "ArrowRight": {
          event.preventDefault();
          setHoverCell((prev) => {
            const current = prev ?? { x: 0, y: 0 };
            return { x: Math.min(board.width - 1, current.x + 1), y: current.y };
          });
          break;
        }
        case "r":
        case "R": {
          event.preventDefault();
          if (activeShape) {
            const rotations = activeShape.rotations;
            const currentIndex = rotations.findIndex(
              (r) => r.rotation === selectedRotation
            );
            const nextIndex = (currentIndex + 1) % rotations.length;
            setSelectedRotation(rotations[nextIndex].rotation);
          }
          break;
        }
        case "Enter":
        case " ": {
          event.preventDefault();
          if (hoverCell) {
            void handlePlace(hoverCell.x, hoverCell.y);
          }
          break;
        }
      }
    }

    window.addEventListener("keydown", handleKeyDown);
    return () => window.removeEventListener("keydown", handleKeyDown);
  });

  useEffect(() => {
    const currentResume = resumeStateRef.current;
    const token = accessTokenRef.current;

    if (!currentResume || !token || socketStatus !== "idle") {
      return;
    }

    const attemptKey = `${currentResume.sessionId}:${token}`;

    if (autoResumeAttemptRef.current === attemptKey) {
      return;
    }

    autoResumeAttemptRef.current = attemptKey;
    setMessage(`Partie duplicate detectee pour le salon ${currentResume.roomCode}.`);
    void connectSocket(true);
  }, [resumeState, socketStatus]);

  async function handleGuestLogin() {
    const token = await ensureGuestToken();
    setMessage(`Invite pret a jouer. Jeton actif : ${token.slice(0, 12)}...`);
  }

  async function handleStartSolo() {
    const token = await ensureGuestToken();

    if (selectedGameType === GameType.ImageRebuild) {
      if (!selectedPuzzleId) {
        setMessage("Choisis un puzzle avant de lancer la partie.");
        return;
      }

      const response = await createSoloImageRebuildSession(
        { puzzleId: selectedPuzzleId },
        token
      );
      setImageState({ loading: false, data: response.state });
      setImageResultState({ loading: false });
      setMessage("Partie solo lancee. Place la premiere brique.");

      if (response.state.finished) {
        await loadSoloResult(response.state.session.sessionId, selectedGameType, token);
      }

      return;
    }

    const response = await createSoloLineBreakerSession({}, token);
    setLineState({ loading: false, data: response.state });
    setLineResultState({ loading: false });
    setMessage("Partie solo lancee. Cherche deja ta premiere ligne.");

    if (response.state.finished) {
      await loadSoloResult(response.state.session.sessionId, selectedGameType, token);
    }
  }

  async function handleRefreshSolo() {
    if (!soloSession || !accessToken) {
      return;
    }

    if (selectedGameType === GameType.ImageRebuild) {
      const response = await fetchSoloImageRebuildSession(
        soloSession.session.sessionId,
        accessToken
      );
      setImageState({ loading: false, data: response.state });

      if (response.state.finished) {
        await loadSoloResult(response.state.session.sessionId, selectedGameType, accessToken);
      }

      return;
    }

    const response = await fetchSoloLineBreakerSession(
      soloSession.session.sessionId,
      accessToken
    );
    setLineState({ loading: false, data: response.state });

    if (response.state.finished) {
      await loadSoloResult(response.state.session.sessionId, selectedGameType, accessToken);
    }
  }

  async function handlePlace(x: number, y: number) {
    if (!activeRotation) {
      return;
    }

    if (selectedMode === GameMode.Solo) {
      if (!soloSession || !accessToken) {
        return;
      }

      if (selectedGameType === GameType.ImageRebuild) {
        const response = await submitSoloImageRebuildPlacement(
          soloSession.session.sessionId,
          {
            anchorX: x,
            anchorY: y,
            rotation: activeRotation.rotation
          },
          accessToken
        );
        setImageState({ loading: false, data: response.state });
        setMessage(
          response.accepted
            ? "Placement accepte. Continue ta mosaique."
            : toFriendlyPlacementError(response.rejectionReason)
        );

        if (response.state.finished) {
          await loadSoloResult(response.state.session.sessionId, selectedGameType, accessToken);
        }

        return;
      }

      const response = await submitSoloLineBreakerPlacement(
        soloSession.session.sessionId,
        {
          anchorX: x,
          anchorY: y,
          rotation: activeRotation.rotation
        },
        accessToken
      );
      setLineState({ loading: false, data: response.state });
      setMessage(
        response.accepted
          ? response.state.metrics.lastClearedCellCount > 0
            ? "Bien joue. Une ligne vient d'etre effacee."
            : "Placement accepte. Continue a preparer tes lignes."
          : toFriendlyPlacementError(response.rejectionReason)
      );

      if (response.state.finished) {
        await loadSoloResult(response.state.session.sessionId, selectedGameType, accessToken);
      }

      return;
    }

    if (!duplicateRoom?.currentOffer) {
      return;
    }

    wsRef.current.send({
      type: "game.place_brick",
      requestId: crypto.randomUUID(),
      payload: {
        sessionId: duplicateRoom.session.sessionId,
        offerId: duplicateRoom.currentOffer.offerId,
        anchorX: x,
        anchorY: y,
        rotation: activeRotation.rotation
      }
    });
  }

  async function handleAbandonSolo() {
    if (!soloSession || !accessToken) {
      return;
    }

    if (selectedGameType === GameType.ImageRebuild) {
      const response = await abandonSoloImageRebuildSession(
        soloSession.session.sessionId,
        accessToken
      );
      setImageState({ loading: false, data: response.state });
      setMessage("La partie a ete arretee.");
      await loadSoloResult(response.state.session.sessionId, selectedGameType, accessToken);
      return;
    }

    const response = await abandonSoloLineBreakerSession(
      soloSession.session.sessionId,
      accessToken
    );
    setLineState({ loading: false, data: response.state });
    setMessage("La partie a ete arretee.");
    await loadSoloResult(response.state.session.sessionId, selectedGameType, accessToken);
  }

  async function ensureDuplicateSocket() {
    if (socketStatus === "authenticated") {
      return true;
    }

    if (socketStatus === "connecting" || socketStatus === "reconnecting") {
      setMessage("Connexion duplicate en cours...");
      return false;
    }

    await connectSocket(Boolean(resumeStateRef.current));
    setMessage("Connexion duplicate initialisee.");
    return false;
  }

  async function handleCreateRoom() {
    if (!(await ensureDuplicateSocket())) {
      return;
    }

    if (selectedGameType === GameType.ImageRebuild && !selectedPuzzleId) {
      setMessage("Choisis un puzzle avant de creer le salon.");
      return;
    }

    wsRef.current.send({
      type: "room.create",
      requestId: crypto.randomUUID(),
      payload: {
        gameType: selectedGameType,
        mode: GameMode.Duplicate2P,
        puzzleId:
          selectedGameType === GameType.ImageRebuild ? selectedPuzzleId : undefined
      }
    });
  }

  async function handleJoinRoom() {
    if (!(await ensureDuplicateSocket()) || !roomCodeInput.trim()) {
      return;
    }

    wsRef.current.send({
      type: "room.join",
      requestId: crypto.randomUUID(),
      payload: {
        roomCode: roomCodeInput.trim().toUpperCase()
      }
    });
  }

  function handleReady() {
    if (!ownPlayer) {
      return;
    }

    wsRef.current.send({
      type: "room.set_ready",
      requestId: crypto.randomUUID(),
      payload: {
        ready: !ownPlayer.ready
      }
    });
  }

  function handleStartRoom() {
    wsRef.current.send({
      type: "room.start",
      requestId: crypto.randomUUID(),
      payload: {}
    });
  }

  function handleLeaveRoom() {
    wsRef.current.send({
      type: "room.leave",
      requestId: crypto.randomUUID(),
      payload: {}
    });
    setDuplicateState({ loading: false });
    setResumeState(null);
    setSocketError("");
    setMessage("Retour a l'accueil duplicate.");
  }

  function handleRequestState() {
    if (!duplicateRoom && !resumeState) {
      return;
    }

    wsRef.current.send({
      type: "game.request_state",
      requestId: crypto.randomUUID(),
      payload: {
        sessionId: duplicateRoom?.session.sessionId ?? resumeState?.sessionId
      }
    });
  }

  function handleChatSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();

    if (!duplicateRoom || !chatInput.trim()) {
      return;
    }

    wsRef.current.send({
      type: "chat.send",
      requestId: crypto.randomUUID(),
      payload: {
        sessionId: duplicateRoom.session.sessionId,
        content: chatInput.trim()
      }
    });
    setChatInput("");
  }

  function handleResetView() {
    setMessage("");
    setSocketError("");
    setImageState({ loading: false });
    setLineState({ loading: false });
    setImageResultState({ loading: false });
    setLineResultState({ loading: false });
    setDuplicateState({ loading: false });
    setResumeState(null);
    setSelectedMode(GameMode.Solo);
  }

  async function handleReplay() {
    if (selectedMode === GameMode.Solo) {
      await handleStartSolo();
      return;
    }

    setDuplicateState({ loading: false });
    setResumeState(null);
    setRoomCodeInput("");
    setChatInput("");
    setMessage("Tu peux creer un nouveau salon duplicate.");
  }

  function renderBoard(board: BoardState | undefined, interactive = false) {
    if (!board) {
      return <div className="puzzle-empty">Aucune grille a afficher.</div>;
    }

    return (
      <div
        className={`board-grid${interactive ? " playable" : ""}`}
        style={{ gridTemplateColumns: `repeat(${board.width}, 1fr)` }}
        onMouseLeave={() => setHoverCell(null)}
      >
        {board.cells.flatMap((row, y) =>
          row.map((color, x) =>
            interactive ? (
              <button
                key={`${x}-${y}`}
                className={`board-cell action${ghostCells.has(`${x}:${y}`) ? " ghost" : ""}`}
                style={{ backgroundColor: resolveBoardColor(color, paletteColors) }}
                onMouseEnter={() => setHoverCell({ x, y })}
                onFocus={() => setHoverCell({ x, y })}
                onClick={() => void handlePlace(x, y)}
                data-keyboard-cursor={hoverCell?.x === x && hoverCell?.y === y ? "true" : undefined}
                disabled={
                  !activeOffer ||
                  (selectedMode === GameMode.Duplicate2P &&
                    duplicateRoom?.phase !== "in_game")
                }
              >
                {!color && ghostCells.has(`${x}:${y}`) ? "." : ""}
              </button>
            ) : (
              <span
                key={`${x}-${y}`}
                className="board-cell fixed"
                style={{ backgroundColor: resolveBoardColor(color, paletteColors) }}
              />
            )
          )
        )}
      </div>
    );
  }

  function renderStatCards(items: Highlight[]) {
    return (
      <div className="stats-grid">
        {items.map((item) => (
          <article key={item.label} className="stat-card">
            <span>{item.label}</span>
            <strong>{item.value}</strong>
          </article>
        ))}
      </div>
    );
  }

  function renderOfferPreview() {
    if (!activeOffer || !activeShape || !activeRotation) {
      return <div className="puzzle-empty">Aucune brique active pour le moment.</div>;
    }

    return (
      <div className="offer-panel">
        <div
          className="offer-grid"
          style={{ gridTemplateColumns: `repeat(${activeRotation.width}, 1fr)` }}
        >
          {Array.from({
            length: activeRotation.width * activeRotation.height
          }).map((_value, index) => {
            const x = index % activeRotation.width;
            const y = Math.floor(index / activeRotation.width);
            const filled = activeRotation.cells.some((cell) => cell.x === x && cell.y === y);

            return (
              <span
                key={`${activeRotation.rotation}-${index}`}
                className={`offer-cell${filled ? " filled" : ""}`}
                style={
                  filled
                    ? {
                        backgroundColor: resolveBoardColor(activeOffer.color, paletteColors)
                      }
                    : undefined
                }
              />
            );
          })}
        </div>
        <div className="rotation-row">
          {activeShape.rotations.map((rotation) => (
            <button
              key={rotation.rotation}
              className={`rotation-chip${activeRotation.rotation === rotation.rotation ? " active" : ""}`}
              onClick={() => setSelectedRotation(rotation.rotation)}
            >
              {rotation.rotation === 0 ? "↑" : rotation.rotation === 90 ? "→" : rotation.rotation === 180 ? "↓" : "←"}
            </button>
          ))}
        </div>
      </div>
    );
  }

  function renderGamePickerCard(gameType: GameType) {
    const content = GAME_CONTENT[gameType];
    const selected = selectedGameType === gameType;

    return (
      <article className={`choice-card${selected ? " selected" : ""}`} key={gameType}>
        <div className="choice-card__header">
          <span className="eyebrow">{content.tag}</span>
          <h3>{content.title}</h3>
        </div>
        <p className="support-copy">{content.shortDescription}</p>
        <div className="choice-card__objective">
          <strong>Objectif</strong>
          <span>{content.objective}</span>
        </div>
        <button
          className={selected ? "secondary-button" : ""}
          onClick={() => setSelectedGameType(gameType)}
        >
          {selected ? "Jeu selectionne" : "Choisir ce jeu"}
        </button>
      </article>
    );
  }

  function renderModeCard(mode: GameMode) {
    const content = MODE_CONTENT[mode];
    const selected = selectedMode === mode;

    return (
      <article className={`mode-card${selected ? " selected" : ""}`} key={mode}>
        <div className="section-heading">
          <span className="section-icon">{toModeIconLabel(mode)}</span>
          <h3>{content.title}</h3>
        </div>
        <p className="support-copy">{content.shortDescription}</p>
        <button
          className={selected ? "secondary-button" : ""}
          onClick={() => setSelectedMode(mode)}
        >
          {selected ? "Mode selectionne" : "Choisir ce mode"}
        </button>
      </article>
    );
  }

  function renderStatusCard(
    title: string,
    body: string,
    tone: "default" | "strong" = "default"
  ) {
    return (
      <article className={`info-card${tone === "strong" ? " info-card--strong" : ""}`}>
        <strong>{title}</strong>
        <span>{body}</span>
      </article>
    );
  }

  function renderSetupBlock(): ReactNode {
    if (selectedMode === GameMode.Solo) {
      return (
        <article className="panel panel-soft">
          <div className="panel-heading">
            <div>
              <p className="eyebrow">Demarrage</p>
              <h2>Comment commencer</h2>
            </div>
          </div>
          <div className="steps-list">
            <div className="step-card">
              <strong>1. Choisis ton jeu</strong>
              <span>{selectedGameContent.title}</span>
            </div>
            <div className="step-card">
              <strong>2. Verifie l'objectif</strong>
              <span>{selectedGameContent.objective}</span>
            </div>
            <div className="step-card">
              <strong>3. Lance la partie</strong>
              <span>{selectedGameContent.setupLabel}</span>
            </div>
          </div>
          {selectedGameType === GameType.ImageRebuild && selectedPuzzle ? (
            <div className="selected-puzzle-card">
              <img src={selectedPuzzle.previewUrl} alt={selectedPuzzle.name} />
              <div>
                <strong>{selectedPuzzle.name}</strong>
                <span>Puzzle 10x10 - niveau {selectedPuzzle.difficulty}</span>
              </div>
            </div>
          ) : null}
        </article>
      );
    }

    return (
      <article className="panel panel-soft">
        <div className="panel-heading">
          <div>
            <p className="eyebrow">Salon duplicate</p>
            <h2>Creer ou rejoindre une partie</h2>
          </div>
        </div>
        <div className="steps-list">
          <div className="step-card">
            <strong>1. Ouvre le mode duplicate</strong>
            <span>Le temps reel sert au salon, au chat et a la synchro des tours.</span>
          </div>
          <div className="step-card">
            <strong>2. Cree un salon ou entre un code</strong>
            <span>Les deux joueurs recevront exactement les memes briques.</span>
          </div>
          <div className="step-card">
            <strong>3. Mettez-vous prets</strong>
            <span>L'hote demarre ensuite la partie pour tout le monde.</span>
          </div>
        </div>
        <div className="room-control-grid room-control-grid--polished">
          <div className="field-group">
            <label htmlFor="roomCode">Code de salon</label>
            <input
              id="roomCode"
              value={roomCodeInput}
              onChange={(event) => setRoomCodeInput(event.target.value.toUpperCase())}
              placeholder="AB12CD"
            />
          </div>
          <div className="field-row">
            <button onClick={() => void connectSocket(Boolean(resumeStateRef.current))}>
              Ouvrir le mode duplicate
            </button>
            <button onClick={() => void handleCreateRoom()}>Creer une partie</button>
            <button onClick={() => void handleJoinRoom()}>Rejoindre</button>
          </div>
          {duplicateRoom ? (
            <div className="field-row">
              <button onClick={handleReady}>
                {ownPlayer?.ready ? "Annuler le pret" : "Je suis pret"}
              </button>
              <button
                onClick={handleStartRoom}
                disabled={
                  !duplicateRoom ||
                  ownPlayer?.seat !== "host" ||
                  duplicateRoom.phase !== "ready"
                }
              >
                Demarrer la partie
              </button>
              <button className="secondary-button" onClick={handleLeaveRoom}>
                Quitter le salon
              </button>
            </div>
          ) : null}
        </div>
      </article>
    );
  }

  function renderHowToPlay() {
    return (
      <article className="panel panel-soft">
        <div className="panel-heading">
          <div>
            <p className="eyebrow">Comment jouer</p>
            <h2>{selectedGameContent.title}</h2>
          </div>
        </div>
        <div className="guide-list">
          {selectedGameContent.howToPlay.map((line) => (
            <div key={line} className="guide-item">
              <span className="guide-dot" />
              <span>{line}</span>
            </div>
          ))}
        </div>
        <div className="help-card">
          <strong>Comment marquer plus de points</strong>
          <span>{selectedGameContent.scoreHint}</span>
        </div>
      </article>
    );
  }

  function renderLobbyState() {
    if (!duplicateRoom) {
      return renderStatusCard(
        "Partie en attente",
        "Cree un salon ou rejoins-en un avec un code pour commencer."
      );
    }

    return (
      <div className="lobby-card">
        <div className="lobby-card__header">
          <strong>Salon {duplicateRoom.roomCode}</strong>
          <span>{toDuplicatePhaseLabel(duplicateRoom.phase)}</span>
        </div>
        <div className="player-status-list">
          {duplicatePlayers.map((player) => (
            <article key={player.playerId} className={`player-card${player.playerId === ownPlayer?.playerId ? " you" : ""}`}>
              <strong>{player.publicAlias}</strong>
              <span>{player.seat === "host" ? "Hote" : "Invite"}</span>
              <span>{player.ready ? "Pret" : "Pas pret"}</span>
              <span>{toConnectionLabel(player.connectionState)}</span>
            </article>
          ))}
        </div>
      </div>
    );
  }

  function renderStatusAndAction() {
    if (selectedMode === GameMode.Solo) {
      const statusLabel = soloSession?.finished
        ? "Partie terminee"
        : soloSession
          ? "Tour en cours"
          : "Pret a lancer";
      const actionLabel = soloSession?.finished
        ? "Consulte ton resultat ou relance une partie."
        : soloSession
          ? selectedGameContent.turnAction
          : selectedGameContent.setupLabel;

      return (
        <>
          {renderStatusCard("Statut", statusLabel, "strong")}
          {renderStatusCard("Consigne", actionLabel)}
          {soloSession
            ? renderStatusCard(
                "Temps du tour",
                `${formatRemainingSeconds(soloSession.turn.deadlineAt, nowMs)} s restantes`
              )
            : null}
        </>
      );
    }

    if (!duplicateRoom) {
      return <>{renderLobbyState()}</>;
    }

    const duplicateStatus =
      duplicateRoom.phase === "in_game"
        ? toTurnStatusLabel(ownPlayer?.turnStatus)
        : toDuplicatePhaseLabel(duplicateRoom.phase);
    const duplicateAction =
      socketStatus === "reconnecting"
        ? "Connexion perdue, tentative de reconnexion..."
        : socketStatus === "closed"
          ? "Connexion interrompue. La reprise automatique peut prendre quelques secondes."
          : duplicateRoom.phase === "waiting"
        ? "Partage le code du salon et attends le deuxieme joueur."
        : duplicateRoom.phase === "ready"
          ? ownPlayer?.seat === "host"
            ? "Tout le monde est pret. L'hote peut demarrer."
            : "L'hote peut maintenant lancer la partie."
          : duplicateRoom.phase === "in_game"
            ? ownPlayer?.turnStatus === "locked"
              ? "Ton placement est envoye. Attends la resolution du tour."
              : selectedGameContent.turnAction
            : "Consulte le resultat et relance une manche si besoin.";

    return (
      <>
        {renderStatusCard("Statut", duplicateStatus, "strong")}
        {renderStatusCard("Consigne", duplicateAction)}
        {renderStatusCard("Connexion", toRealtimeStatusLabel(socketStatus))}
        {renderStatusCard(
          "Temps du tour",
          `${formatRemainingSeconds(duplicateRoom.turn.deadlineAt, nowMs)} s restantes`
        )}
      </>
    );
  }

  function renderMainBoards() {
    const targetLabel =
      selectedMode === GameMode.Duplicate2P &&
      duplicateRoom?.gameType === GameType.ImageRebuild
        ? duplicateRoom.puzzleName
        : selectedGameContent.title;

    return (
      <div className="board-stage">
        <div className="board-stage__header">
          <div>
            <p className="eyebrow">{selectedModeContent.title}</p>
            <h2>{selectedGameContent.title}</h2>
          </div>
          <div className="badge-row">
            <span className="pill">
              {selectedMode === GameMode.Solo
                ? soloSession?.finished
                  ? "Partie terminee"
                  : "Tour en cours"
                : toDuplicatePhaseLabel(duplicateRoom?.phase)}
            </span>
            <span className="pill">
              Score {selectedMode === GameMode.Solo ? soloSession?.score.score ?? 0 : ownPlayer?.score ?? 0}
            </span>
          </div>
        </div>
        <div className={`board-columns${selectedMode === GameMode.Duplicate2P ? "" : " board-columns--solo"}`}>
          {targetBoard ? (
            <section className="board-column">
              <div className="board-header">
                <h3>Image cible</h3>
                <p>{targetLabel}</p>
              </div>
              {renderBoard(targetBoard)}
            </section>
          ) : null}
          <section className="board-column board-column--focus">
            <div className="board-header">
              <h3>{selectedMode === GameMode.Duplicate2P ? "Ton plateau" : "Plateau joueur"}</h3>
              <p>
                {selectedMode === GameMode.Duplicate2P
                  ? toTurnStatusLabel(ownPlayer?.turnStatus)
                  : soloSession
                    ? `Tour ${soloSession.turn.index}`
                    : "En attente"}
              </p>
            </div>
            {renderBoard(playerBoard, true)}
          </section>
          {selectedMode === GameMode.Duplicate2P ? (
            <section className="board-column">
              <div className="board-header">
                <h3>Adversaire</h3>
                <p>{opponent?.publicAlias ?? "Aucun joueur"}</p>
              </div>
              {renderBoard(opponentBoard)}
            </section>
          ) : (
            <section className="board-column">
              <div className="board-header">
                <h3>Objectif du tour</h3>
                <p>Action attendue</p>
              </div>
              <div className="goal-card">
                <strong>{selectedGameContent.objective}</strong>
                <span>{selectedGameContent.turnAction}</span>
              </div>
            </section>
          )}
        </div>
      </div>
    );
  }

  function renderResultPanel() {
    const isSoloFinished = Boolean(soloSession?.finished);
    const isDuplicateFinished = duplicateRoom?.phase === "finished";

    if (!isSoloFinished && !isDuplicateFinished) {
      return null;
    }

    const resultTitle =
      selectedMode === GameMode.Solo
        ? "Partie terminee"
        : ownDuplicateResult
          ? toOutcomeLabel(ownDuplicateResult.outcome)
          : "Resultat";
    const resultHighlights =
      selectedMode === GameMode.Solo
        ? [
            { label: "Score final", value: String(soloResult?.result?.score ?? soloSession?.result?.score ?? 0) },
            { label: "Points gagnes", value: String(currentRewardPoints ?? 0) },
            { label: "Fin de partie", value: toFinishReasonLabel(soloResult?.finishReason ?? soloSession?.finishReason) },
            { label: "Mode", value: "Solo" }
          ]
        : [
            { label: "Ton resultat", value: toOutcomeLabel(ownDuplicateResult?.outcome) },
            { label: "Ton score", value: String(ownDuplicateResult?.score ?? ownPlayer?.score ?? 0) },
            { label: "Score adverse", value: String(opponentDuplicateResult?.score ?? opponent?.score ?? 0) },
            { label: "Points gagnes", value: String(currentRewardPoints ?? 0) }
          ];

    return (
      <section className="panel result-panel">
        <div className="panel-heading">
          <div>
            <p className="eyebrow">Resultat</p>
            <div className="section-heading">
              <span className="section-icon">FIN</span>
              <h2>{resultTitle}</h2>
            </div>
          </div>
          <span className="pill">
            {selectedMode === GameMode.Solo
              ? toFinishReasonLabel(soloResult?.finishReason ?? soloSession?.finishReason)
              : toFinishReasonLabel(duplicateRoom?.result?.finishReason)}
          </span>
        </div>
        {renderStatCards(resultHighlights)}
        {selectedMode === GameMode.Duplicate2P && duplicateRoom?.result ? (
          <div className="result-compare">
            {duplicateRoom.result.players.map((player) => (
              <article key={player.playerId} className={`result-card${player.playerId === ownPlayer?.playerId ? " result-card--active" : ""}`}>
                <strong>{player.publicAlias}</strong>
                <span>{toOutcomeLabel(player.outcome)}</span>
                <span>Score {player.score}</span>
                <span>{toFinishReasonLabel(player.finishReason)}</span>
              </article>
            ))}
          </div>
        ) : null}
        {!loyaltyBalanceState.data?.summary.technicalLoyaltyId ? (
          <div className="guest-invite-banner">
            <p>
              Tu joues en tant qu'invite. Cree un compte sur la boutique pour conserver tes
              points de fidelite et profiter de reductions exclusives !
            </p>
            <a
              href={`${PHP_SHOP_URL}/register.php?loyaltyRef=${encodeURIComponent(currentPlayerId ?? "")}`}
              className="cta-button"
              target="_blank"
              rel="noopener noreferrer"
            >
              Creer mon compte sur la boutique
            </a>
          </div>
        ) : null}
        <div className="hero-actions">
          <button onClick={() => void handleReplay()}>Rejouer</button>
          <button className="secondary-button" onClick={handleResetView}>Retour a l'accueil</button>
        </div>
      </section>
    );
  }

  function renderHistoryPanel() {
    return (
      <article className="panel">
        <div className="panel-heading">
          <div>
            <p className="eyebrow">Historique</p>
            <div className="section-heading">
              <span className="section-icon">HIS</span>
              <h2>Parties recentes</h2>
            </div>
          </div>
          {historyState.loading ? <span className="pill">Chargement</span> : null}
        </div>
        <div className="history-list">
          {(historyState.data?.items ?? []).map((entry) => (
            <button
              key={entry.historyId}
              className={`history-card${activeHistoryItem?.historyId === entry.historyId ? " active" : ""}`}
              onClick={() => void loadHistoryDetailItem(entry.historyId)}
            >
              <strong>{toHistoryLabel(entry)}</strong>
              <span>Score final {entry.score}</span>
              <span>{toOutcomeLabel(entry.outcome)} - {toFinishReasonLabel(entry.finishReason)}</span>
              <small>{formatDateTime(entry.endedAt)} - {formatDuration(entry.durationMs)}</small>
            </button>
          ))}
          {!historyState.data?.items.length ? (
            <div className="puzzle-empty">
              {historyState.error ?? "Aucune partie enregistree pour le moment."}
            </div>
          ) : null}
        </div>
        <div className="detail-card">
          {historyDetailState.data ? (
            <>
              {renderStatCards([
                { label: "Score final", value: String(historyDetailState.data.item.score) },
                { label: "Fidelite", value: `${historyDetailState.data.item.rewardPoints ?? 0} pts` },
                { label: "Mode", value: historyDetailState.data.item.mode === GameMode.Solo ? "Solo" : "Duplicate" },
                { label: "Duree", value: formatDuration(historyDetailState.data.item.durationMs) }
              ])}
              {renderStatCards(toHistoryHighlights(historyDetailState.data))}
              <div className="loyalty-entry-list">
                {historyDetailState.data.loyaltyEntries.map((entry) => (
                  <article key={entry.entryId} className="info-card">
                    <strong>{entry.pointsDelta > 0 ? `+${entry.pointsDelta}` : entry.pointsDelta} pts</strong>
                    <span>{entry.reason}</span>
                  </article>
                ))}
              </div>
            </>
          ) : (
            <div className="puzzle-empty">
              {historyDetailState.error ?? "Selectionne une partie pour afficher son detail."}
            </div>
          )}
        </div>
      </article>
    );
  }

  function renderLoyaltyPanel() {
    return (
      <article className="panel">
        <div className="panel-heading">
          <div>
            <p className="eyebrow">Fidelite</p>
            <div className="section-heading">
              <span className="section-icon">PTS</span>
              <h2>Points et recompenses</h2>
            </div>
          </div>
          {loyaltyBalanceState.loading ? <span className="pill">Chargement</span> : null}
        </div>
        {loyaltyBalanceState.data ? (
          <>
            {renderStatCards([
              { label: "Solde", value: `${loyaltyBalanceState.data.summary.balance} pts` },
              { label: "Cumule", value: `${loyaltyBalanceState.data.summary.lifetimeEarned} pts` },
              { label: "Ecritures", value: String(loyaltyBalanceState.data.summary.totalEntries) },
              {
                label: "Profil",
                value: loyaltyBalanceState.data.summary.technicalLoyaltyId
                  ? "Compte relie"
                  : "Joueur invite"
              }
            ])}
            <div className="history-list">
              {(loyaltyLedgerState.data?.items ?? []).slice(0, 6).map((entry) => (
                <article key={entry.entryId} className="history-card compact">
                  <strong>{entry.pointsDelta > 0 ? "+" : ""}{entry.pointsDelta} pts</strong>
                  <span>{entry.reason}</span>
                  <small>{formatDateTime(entry.createdAt)}</small>
                </article>
              ))}
            </div>
          </>
        ) : (
          <div className="puzzle-empty">
            {loyaltyBalanceState.error ?? "Connecte un joueur pour voir les points de fidelite."}
          </div>
        )}
      </article>
    );
  }

  function renderDebugPanel() {
    if (!DEBUG_AVAILABLE) {
      return null;
    }

    return (
      <section className="panel debug-panel">
        <div className="panel-heading">
          <div>
            <p className="eyebrow">Debug</p>
            <h2>Informations techniques</h2>
          </div>
          <button className="secondary-button" onClick={() => setDebugOpen((value) => !value)}>
            {debugOpen ? "Masquer" : "Afficher"}
          </button>
        </div>
        {debugOpen ? (
          <div className="debug-grid">
            <article className="debug-card">
              <strong>Health</strong>
              <pre>{formatJson(healthState.data ?? healthState.error ?? {})}</pre>
            </article>
            <article className="debug-card">
              <strong>Bootstrap</strong>
              <pre>{formatJson(bootstrapState.data ?? bootstrapState.error ?? {})}</pre>
            </article>
            <article className="debug-card">
              <strong>Session active</strong>
              <pre>{formatJson(selectedMode === GameMode.Solo ? soloSession ?? {} : duplicateRoom ?? resumeState ?? {})}</pre>
            </article>
            <article className="debug-card">
              <strong>Historique detail</strong>
              <pre>{formatJson(historyDetailState.data ?? {})}</pre>
            </article>
          </div>
        ) : null}
      </section>
    );
  }

  const onboardingMessage = "Jouez et gagnez des points pour obtenir des reductions sur la boutique !";
  const boardHighlights = toBoardHighlights(
    selectedGameType,
    currentScore,
    currentImageMetrics,
    currentLineMetrics,
    currentLineStats
  );
  const contextualHint = toContextualHint(
    selectedGameType,
    selectedMode,
    Boolean(targetBoard)
  );

  return (
    <main className="page-shell">
      <section className="hero-panel">
        <div className="hero-topline">
          <div>
            <p className="eyebrow">BrickMosaic</p>
            <h1>{selectedGameContent.title}</h1>
            <p className="hero-copy">
              {selectedGameContent.shortDescription} {onboardingMessage}
            </p>
          </div>
          <div className="hero-badges">
            <span className="pill">{selectedModeContent.title}</span>
            {currentRewardPoints !== undefined ? <span className="pill">+{currentRewardPoints} pts gagnes</span> : null}
            {selectedMode === GameMode.Duplicate2P ? (
              <span className={`pill realtime-pill ${socketStatus}`}>
                {toRealtimeStatusLabel(socketStatus)}
              </span>
            ) : null}
          </div>
        </div>
        <div className="choice-grid">{renderGamePickerCard(GameType.ImageRebuild)}{renderGamePickerCard(GameType.LineBreaker)}</div>
        <div className="mode-grid">{renderModeCard(GameMode.Solo)}{renderModeCard(GameMode.Duplicate2P)}</div>

        {/* Rules section */}
        <div className="rules-section">
          <h3>Regles du jeu  - {selectedGameContent.title}</h3>
          <ol className="rules-list">
            {selectedGameContent.rules.map((rule, i) => (
              <li key={i} data-step={i + 1}>{rule}</li>
            ))}
          </ol>
        </div>

        {/* Points explanation section */}
        <div className="points-section">
          <h3>{POINTS_EXPLANATION.title}</h3>
          <ul style={{margin: 0, paddingLeft: "1.2rem", lineHeight: 1.8}}>
            {POINTS_EXPLANATION.howToEarn.map((line, i) => (
              <li key={i}>{line}</li>
            ))}
          </ul>
          <div className="points-tiers">
            {POINTS_EXPLANATION.tiers.map((tier) => (
              <div key={tier.points} className="tier-badge">
                <strong>{tier.discount}</strong>
                <span>{tier.points} pts</span>
              </div>
            ))}
          </div>
          <p style={{marginTop: "0.75rem", fontWeight: 700, fontSize: "0.95rem", color: "var(--primary)"}}>
            {POINTS_EXPLANATION.callToAction}
          </p>
          {loyaltyBalanceState.data?.summary ? (
            <p style={{marginTop: "0.5rem", fontSize: "1.1rem", fontWeight: 800}}>
              Votre solde : {loyaltyBalanceState.data.summary.balance} pts
            </p>
          ) : null}
        </div>

        {message ? <p className="inline-message">{message}</p> : null}
        {socketError ? <p className="inline-message error">{socketError}</p> : null}
      </section>

      <section className="workspace-grid workspace-grid--wide">
        <div className="stack-column">
          {renderSetupBlock()}
          {renderHowToPlay()}
          <article className="panel panel-soft">
            <div className="panel-heading">
              <div>
                <p className="eyebrow">A vous de jouer</p>
                <h2>Brique a placer</h2>
              </div>
              {selectedMode === GameMode.Duplicate2P ? (
                <span className={`pill realtime-pill ${socketStatus}`}>
                  {toRealtimeStatusLabel(socketStatus)}
                </span>
              ) : null}
            </div>
            <p className="support-copy">{selectedGameContent.turnAction}</p>
            <p className="keyboard-hint">Clavier : fleches pour deplacer, R pour tourner, Entree pour poser</p>
            {renderOfferPreview()}
            {renderStatusAndAction()}
            {renderStatCards(boardHighlights)}
            {selectedMode === GameMode.Solo ? (
              <div className="hero-actions">
                <button onClick={() => void handleStartSolo()}>
                  {soloSession && !soloSession.finished ? "Relancer une nouvelle partie" : "Lancer la partie"}
                </button>
                <button className="secondary-button" onClick={() => void handleRefreshSolo()} disabled={!soloSession}>Recharger l'etat</button>
                <button className="secondary-button" onClick={() => void handleAbandonSolo()} disabled={!soloSession || soloSession.finished}>Arreter la partie</button>
              </div>
            ) : duplicateRoom ? (
              <div className="hero-actions">
                <button onClick={handleRequestState}>Mettre a jour le salon</button>
                <button className="secondary-button" onClick={handleReady}>{ownPlayer?.ready ? "Annuler le pret" : "Je suis pret"}</button>
                <button className="secondary-button" onClick={handleLeaveRoom}>Quitter le salon</button>
              </div>
            ) : null}
          </article>
          {selectedMode === GameMode.Duplicate2P && duplicateRoom ? (
            <article className="panel panel-soft">
              <div className="panel-heading">
                <div>
                  <p className="eyebrow">Chat</p>
                  <h2>Discuter pendant la partie</h2>
                </div>
              </div>
              <form className="chat-form" onSubmit={handleChatSubmit}>
                <input value={chatInput} onChange={(event) => setChatInput(event.target.value)} placeholder="Ecris un message court pour ton adversaire" maxLength={240} />
                <button type="submit" disabled={!duplicateRoom}>Envoyer</button>
              </form>
              <div className="chat-log">
                {duplicateRoom.chatMessages.length ? (
                  duplicateRoom.chatMessages.map((entry) => (
                    <article key={entry.messageId} className="chat-entry">
                      <strong>{entry.publicAlias}</strong>
                      <span>{entry.content}</span>
                      <small>{formatDateTime(entry.createdAt)}</small>
                    </article>
                  ))
                ) : (
                  <div className="puzzle-empty">Aucun message pour le moment.</div>
                )}
              </div>
            </article>
          ) : null}
        </div>
        <div className="stack-column">
          {renderMainBoards()}
          {renderResultPanel()}
        </div>
      </section>

      <section className="meta-grid">
        {renderHistoryPanel()}
        {renderLoyaltyPanel()}
      </section>

      {/* Debug panel removed for clean user experience */}
    </main>
  );
}
