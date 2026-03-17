import {
  BrickOffer,
  BoardState,
  GameSessionSummary,
  TechnicalPlayerIdentity
} from "../domain/common.js";
import {
  ChatMessageView,
  DuplicateRoomState,
  DuplicateTurnResolution
} from "../domain/duplicate.js";
import { GameMode, GameType } from "../enums.js";

export interface WsEnvelope<TType extends string, TPayload> {
  type: TType;
  requestId?: string;
  serverTime?: number;
  payload: TPayload;
}

export type ClientWsMessage =
  | WsEnvelope<"session.authenticate", { token: string }>
  | WsEnvelope<"session.resume", { sessionId: string; resumeToken: string }>
  | WsEnvelope<"solo.create", { gameType: GameType }>
  | WsEnvelope<
      "room.create",
      { gameType: GameType; mode: GameMode; puzzleId?: string; seed?: string }
    >
  | WsEnvelope<"room.join", { roomCode: string }>
  | WsEnvelope<"room.leave", Record<string, never>>
  | WsEnvelope<"room.set_ready", { ready: boolean }>
  | WsEnvelope<"room.start", Record<string, never>>
  | WsEnvelope<"chat.send", { sessionId?: string; content: string }>
  | WsEnvelope<
      "game.place_brick",
      {
        sessionId: string;
        offerId: string;
        anchorX: number;
        anchorY: number;
        rotation: number;
      }
    >
  | WsEnvelope<"game.request_state", { sessionId?: string }>
  | WsEnvelope<"ping", { clientTime?: number }>;

export type ServerWsMessage =
  | WsEnvelope<"session.authenticated", { player: TechnicalPlayerIdentity }>
  | WsEnvelope<
      "session.resumed",
      { session: GameSessionSummary; state: DuplicateRoomState; resumeToken: string }
    >
  | WsEnvelope<
      "room.created",
      { roomCode: string; sessionId: string; state: DuplicateRoomState; resumeToken: string }
    >
  | WsEnvelope<
      "room.joined",
      { roomCode: string; sessionId: string; state: DuplicateRoomState; resumeToken: string }
    >
  | WsEnvelope<"room.state", { state: DuplicateRoomState }>
  | WsEnvelope<"room.player_joined", { player: TechnicalPlayerIdentity; state: DuplicateRoomState }>
  | WsEnvelope<"room.player_left", { playerId: string }>
  | WsEnvelope<
      "room.player_ready_changed",
      { playerId: string; ready: boolean; state: DuplicateRoomState }
    >
  | WsEnvelope<"room.chat_message", { message: ChatMessageView }>
  | WsEnvelope<"game.started", { state: DuplicateRoomState }>
  | WsEnvelope<"game.state", { state: DuplicateRoomState }>
  | WsEnvelope<"game.turn_started", { state: DuplicateRoomState }>
  | WsEnvelope<
      "game.turn_resolved",
      { state: DuplicateRoomState; resolution: DuplicateTurnResolution }
    >
  | WsEnvelope<"game.action_rejected", { reason: string }>
  | WsEnvelope<
      "game.player_disconnected",
      { playerId: string; graceDeadlineAt?: string; state: DuplicateRoomState }
    >
  | WsEnvelope<"game.player_reconnected", { playerId: string; state: DuplicateRoomState }>
  | WsEnvelope<"game.finished", { state: DuplicateRoomState }>
  | WsEnvelope<"error", { code: string; message: string }>
  | WsEnvelope<"pong", { clientTime?: number }>;
