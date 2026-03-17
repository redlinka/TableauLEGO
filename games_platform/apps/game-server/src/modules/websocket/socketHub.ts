import { IncomingMessage, Server } from "node:http";
import {
  ClientWsMessage,
  DuplicateRoomState,
  GameMode,
  ServerWsMessage,
  TechnicalPlayerIdentity
} from "@games-platform/game-contracts";
import { WebSocketServer, WebSocket } from "ws";
import { AppConfig } from "../../config/env.js";
import { DuplicateSessionError, DuplicateSessionService } from "../../domain/duplicate/duplicateSessionService.js";
import { RepositoryBundle } from "../../storage/mongoose/repositories/index.js";
import { TokenService } from "../auth/tokenService.js";

type ClientContext = {
  identity?: TechnicalPlayerIdentity;
  sessionId?: string;
  baseUrl: string;
};

export class WebSocketHub {
  private server?: WebSocketServer;
  private readonly contexts = new Map<WebSocket, ClientContext>();
  private readonly playerSockets = new Map<string, Set<WebSocket>>();
  private readonly sessionSockets = new Map<string, Set<WebSocket>>();

  constructor(
    private readonly config: AppConfig,
    private readonly repositories: RepositoryBundle,
    private readonly tokenService: TokenService,
    private readonly duplicateSessionService: DuplicateSessionService
  ) {}

  attach(server: Server): void {
    this.server = new WebSocketServer({
      server,
      path: this.config.wsPath
    });

    this.server.on("connection", (socket, request) => {
      this.contexts.set(socket, {
        baseUrl: this.buildBaseUrl(request)
      });

      socket.on("message", async (data) => {
        await this.handleMessage(socket, data.toString("utf8"));
      });

      socket.on("close", () => {
        void this.handleClose(socket).catch(() => undefined);
      });
    });
  }

  broadcastToSession(sessionId: string, message: unknown): void {
    const sockets = this.sessionSockets.get(sessionId);

    if (!sockets) {
      return;
    }

    for (const socket of sockets) {
      this.send(socket, message as ServerWsMessage);
    }
  }

  sendToPlayer(playerId: string, message: unknown): void {
    const sockets = this.playerSockets.get(playerId);

    if (!sockets) {
      return;
    }

    for (const socket of sockets) {
      this.send(socket, message as ServerWsMessage);
    }
  }

  async close(): Promise<void> {
    if (!this.server) {
      return;
    }

    const server = this.server;
    this.server = undefined;

    for (const socket of this.contexts.keys()) {
      try {
        socket.removeAllListeners();
        socket.terminate();
      } catch {
        // Ignore socket teardown failures during shutdown.
      }
    }

    for (const socket of server.clients) {
      try {
        socket.removeAllListeners();
        socket.terminate();
      } catch {
        // Ignore socket teardown failures during shutdown.
      }
    }

    this.contexts.clear();
    this.playerSockets.clear();
    this.sessionSockets.clear();

    await new Promise<void>((resolve, reject) => {
      let settled = false;
      const settle = (error?: Error) => {
        if (settled) {
          return;
        }

        settled = true;

        if (error) {
          reject(error);
          return;
        }

        resolve();
      };

      const timeout = setTimeout(() => {
        settle();
      }, 250);

      server.close((error) => {
        clearTimeout(timeout);

        if (error) {
          settle(error);
          return;
        }

        settle();
      });
    });
  }

  private async handleMessage(socket: WebSocket, rawData: string): Promise<void> {
    let message: ClientWsMessage;

    try {
      message = JSON.parse(rawData) as ClientWsMessage;
    } catch {
      this.sendError(socket, undefined, "INVALID_JSON", "WebSocket payload must be valid JSON.");
      return;
    }

    try {
      switch (message.type) {
        case "session.authenticate":
          await this.handleAuthenticate(socket, message);
          return;
        case "session.resume":
          await this.handleResume(socket, message);
          return;
        case "room.create":
          await this.handleRoomCreate(socket, message);
          return;
        case "room.join":
          await this.handleRoomJoin(socket, message);
          return;
        case "room.leave":
          await this.handleRoomLeave(socket, message);
          return;
        case "room.set_ready":
          await this.handleRoomSetReady(socket, message);
          return;
        case "room.start":
          await this.handleRoomStart(socket, message);
          return;
        case "chat.send":
          await this.handleChatSend(socket, message);
          return;
        case "game.place_brick":
          await this.handlePlaceBrick(socket, message);
          return;
        case "game.request_state":
          await this.handleRequestState(socket, message);
          return;
        case "ping":
          this.send(socket, {
            type: "pong",
            requestId: message.requestId,
            serverTime: Date.now(),
            payload: {
              clientTime: message.payload.clientTime
            }
          });
          return;
        default:
          this.sendError(
            socket,
            message.requestId,
            "NOT_IMPLEMENTED",
            `Message '${message.type}' is not implemented.`
          );
      }
    } catch (error) {
      if (error instanceof DuplicateSessionError) {
        this.sendError(socket, message.requestId, error.code, error.message);
        return;
      }

      this.sendError(socket, message.requestId, "UNEXPECTED_ERROR", "Unexpected WebSocket error.");
    }
  }

  private async handleAuthenticate(
    socket: WebSocket,
    message: Extract<ClientWsMessage, { type: "session.authenticate" }>
  ): Promise<void> {
    const verified = await this.tokenService.verifyAccessToken(message.payload.token);
    const identity = await this.repositories.players.ensureTechnicalPlayer(verified.identity);
    this.bindSocket(socket, {
      identity
    });
    this.send(socket, {
      type: "session.authenticated",
      requestId: message.requestId,
      serverTime: Date.now(),
      payload: {
        player: identity
      }
    });
  }

  private async handleResume(
    socket: WebSocket,
    message: Extract<ClientWsMessage, { type: "session.resume" }>
  ): Promise<void> {
    const context = this.contexts.get(socket);

    if (!context) {
      return;
    }

    const resumed = await this.duplicateSessionService.resumeSession({
      sessionId: message.payload.sessionId,
      resumeToken: message.payload.resumeToken,
      baseUrl: context.baseUrl
    });

    this.bindSocket(socket, {
      identity: resumed.player,
      sessionId: resumed.state.session.sessionId
    });
    this.send(socket, {
      type: "session.resumed",
      requestId: message.requestId,
      serverTime: Date.now(),
      payload: {
        session: resumed.state.session,
        state: resumed.state,
        resumeToken: resumed.resumeToken
      }
    });
  }

  private async handleRoomCreate(
    socket: WebSocket,
    message: Extract<ClientWsMessage, { type: "room.create" }>
  ): Promise<void> {
    const identity = this.requireIdentity(socket);

    if (message.payload.mode !== GameMode.Duplicate2P) {
      throw new DuplicateSessionError(
        "UNSUPPORTED_MODE",
        "Duplicate WebSocket rooms only support duplicate_2p."
      );
    }

    const context = this.requireContext(socket);
    const created = await this.duplicateSessionService.createRoom({
      player: identity,
      baseUrl: context.baseUrl,
      gameType: message.payload.gameType,
      puzzleId: message.payload.puzzleId,
      seed: message.payload.seed
    });

    this.bindSocket(socket, {
      identity,
      sessionId: created.state.session.sessionId
    });
    this.send(socket, {
      type: "room.created",
      requestId: message.requestId,
      serverTime: Date.now(),
      payload: {
        roomCode: created.state.roomCode,
        sessionId: created.state.session.sessionId,
        state: created.state,
        resumeToken: created.resumeToken
      }
    });
  }

  private async handleRoomJoin(
    socket: WebSocket,
    message: Extract<ClientWsMessage, { type: "room.join" }>
  ): Promise<void> {
    const identity = this.requireIdentity(socket);
    const context = this.requireContext(socket);
    const joined = await this.duplicateSessionService.joinRoom({
      player: identity,
      baseUrl: context.baseUrl,
      roomCode: message.payload.roomCode
    });

    this.bindSocket(socket, {
      identity,
      sessionId: joined.state.session.sessionId
    });
    this.send(socket, {
      type: "room.joined",
      requestId: message.requestId,
      serverTime: Date.now(),
      payload: {
        roomCode: joined.state.roomCode,
        sessionId: joined.state.session.sessionId,
        state: joined.state,
        resumeToken: joined.resumeToken
      }
    });
    this.broadcastToSession(joined.state.session.sessionId, {
      type: "room.player_joined",
      serverTime: Date.now(),
      payload: {
        player: joined.joinedPlayer,
        state: joined.state
      }
    });
    this.broadcastToSession(joined.state.session.sessionId, {
      type: "room.state",
      serverTime: Date.now(),
      payload: {
        state: joined.state
      }
    });
  }

  private async handleRoomLeave(
    socket: WebSocket,
    message: Extract<ClientWsMessage, { type: "room.leave" }>
  ): Promise<void> {
    const identity = this.requireIdentity(socket);
    const context = this.requireContext(socket);

    if (!context.sessionId) {
      throw new DuplicateSessionError("NO_ACTIVE_ROOM", "This socket is not bound to a room.");
    }

    const leftState = await this.duplicateSessionService.leaveRoom({
      player: identity,
      baseUrl: context.baseUrl,
      sessionId: context.sessionId
    });

    this.unbindSocket(socket);
    this.bindSocket(socket, {
      identity
    });
    this.broadcastToSession(leftState.session.sessionId, {
      type: "room.player_left",
      requestId: message.requestId,
      serverTime: Date.now(),
      payload: {
        playerId: identity.playerId
      }
    });
    this.broadcastToSession(leftState.session.sessionId, {
      type: "room.state",
      serverTime: Date.now(),
      payload: {
        state: leftState
      }
    });
  }

  private async handleRoomSetReady(
    socket: WebSocket,
    message: Extract<ClientWsMessage, { type: "room.set_ready" }>
  ): Promise<void> {
    const identity = this.requireIdentity(socket);
    const context = this.requireContext(socket);

    if (!context.sessionId) {
      throw new DuplicateSessionError("NO_ACTIVE_ROOM", "This socket is not bound to a room.");
    }

    const state = await this.duplicateSessionService.setReady({
      player: identity,
      baseUrl: context.baseUrl,
      sessionId: context.sessionId,
      ready: message.payload.ready
    });

    this.broadcastToSession(context.sessionId, {
      type: "room.player_ready_changed",
      requestId: message.requestId,
      serverTime: Date.now(),
      payload: {
        playerId: identity.playerId,
        ready: message.payload.ready,
        state
      }
    });
    this.broadcastToSession(context.sessionId, {
      type: "room.state",
      serverTime: Date.now(),
      payload: {
        state
      }
    });
  }

  private async handleRoomStart(
    socket: WebSocket,
    message: Extract<ClientWsMessage, { type: "room.start" }>
  ): Promise<void> {
    const identity = this.requireIdentity(socket);
    const context = this.requireContext(socket);

    if (!context.sessionId) {
      throw new DuplicateSessionError("NO_ACTIVE_ROOM", "This socket is not bound to a room.");
    }

    const state = await this.duplicateSessionService.startRoom({
      player: identity,
      baseUrl: context.baseUrl,
      sessionId: context.sessionId
    });

    this.broadcastToSession(context.sessionId, {
      type: "game.started",
      requestId: message.requestId,
      serverTime: Date.now(),
      payload: {
        state
      }
    });
    if (state.phase === "in_game") {
      this.broadcastToSession(context.sessionId, {
        type: "game.turn_started",
        serverTime: Date.now(),
        payload: {
          state
        }
      });
    }
  }

  private async handleChatSend(
    socket: WebSocket,
    message: Extract<ClientWsMessage, { type: "chat.send" }>
  ): Promise<void> {
    const identity = this.requireIdentity(socket);
    const context = this.requireContext(socket);
    const sessionId = message.payload.sessionId ?? context.sessionId;

    if (!sessionId) {
      throw new DuplicateSessionError("NO_ACTIVE_ROOM", "This socket is not bound to a room.");
    }

    await this.duplicateSessionService.sendChat({
      player: identity,
      baseUrl: context.baseUrl,
      sessionId,
      content: message.payload.content
    });
  }

  private async handlePlaceBrick(
    socket: WebSocket,
    message: Extract<ClientWsMessage, { type: "game.place_brick" }>
  ): Promise<void> {
    const identity = this.requireIdentity(socket);
    const context = this.requireContext(socket);
    const result = await this.duplicateSessionService.submitPlacement({
      player: identity,
      baseUrl: context.baseUrl,
      sessionId: message.payload.sessionId,
      offerId: message.payload.offerId,
      anchorX: message.payload.anchorX,
      anchorY: message.payload.anchorY,
      rotation: message.payload.rotation
    });

    if (!result.accepted) {
      this.send(socket, {
        type: "game.action_rejected",
        requestId: message.requestId,
        serverTime: Date.now(),
        payload: {
          reason: result.rejectionReason ?? "rejected"
        }
      });
    }
  }

  private async handleRequestState(
    socket: WebSocket,
    message: Extract<ClientWsMessage, { type: "game.request_state" }>
  ): Promise<void> {
    const identity = this.requireIdentity(socket);
    const context = this.requireContext(socket);
    const sessionId = message.payload.sessionId ?? context.sessionId;

    if (!sessionId) {
      throw new DuplicateSessionError("NO_ACTIVE_ROOM", "This socket is not bound to a room.");
    }

    const state = await this.duplicateSessionService.getRoomState({
      playerId: identity.playerId,
      sessionId,
      baseUrl: context.baseUrl
    });

    this.send(socket, {
      type: state.phase === "in_game" || state.phase === "finished" ? "game.state" : "room.state",
      requestId: message.requestId,
      serverTime: Date.now(),
      payload: {
        state
      }
    });
  }

  private async handleClose(socket: WebSocket): Promise<void> {
    const context = this.contexts.get(socket);

    this.unbindSocket(socket);
    this.contexts.delete(socket);

    if (!context?.identity || !context.sessionId) {
      return;
    }

    if (this.hasActiveSocketForSession(context.identity.playerId, context.sessionId)) {
      return;
    }

    await this.duplicateSessionService.handleSocketDisconnected({
      sessionId: context.sessionId,
      playerId: context.identity.playerId,
      baseUrl: context.baseUrl
    });
  }

  private bindSocket(
    socket: WebSocket,
    input: {
      identity?: TechnicalPlayerIdentity;
      sessionId?: string;
    }
  ): void {
    const current = this.contexts.get(socket);

    if (!current) {
      return;
    }

    this.unbindSocket(socket);
    const next: ClientContext = {
      ...current,
      identity: input.identity ?? current.identity,
      sessionId: input.sessionId
    };
    this.contexts.set(socket, next);

    if (next.identity) {
      if (!this.playerSockets.has(next.identity.playerId)) {
        this.playerSockets.set(next.identity.playerId, new Set());
      }

      this.playerSockets.get(next.identity.playerId)?.add(socket);
    }

    if (next.sessionId) {
      if (!this.sessionSockets.has(next.sessionId)) {
        this.sessionSockets.set(next.sessionId, new Set());
      }

      this.sessionSockets.get(next.sessionId)?.add(socket);
    }
  }

  private unbindSocket(socket: WebSocket): void {
    const context = this.contexts.get(socket);

    if (!context) {
      return;
    }

    if (context.identity) {
      const sockets = this.playerSockets.get(context.identity.playerId);

      sockets?.delete(socket);

      if (sockets && sockets.size === 0) {
        this.playerSockets.delete(context.identity.playerId);
      }
    }

    if (context.sessionId) {
      const sockets = this.sessionSockets.get(context.sessionId);

      sockets?.delete(socket);

      if (sockets && sockets.size === 0) {
        this.sessionSockets.delete(context.sessionId);
      }
    }
  }

  private hasActiveSocketForSession(playerId: string, sessionId: string): boolean {
    const sockets = this.playerSockets.get(playerId);

    if (!sockets) {
      return false;
    }

    for (const socket of sockets) {
      const context = this.contexts.get(socket);

      if (context?.sessionId === sessionId) {
        return true;
      }
    }

    return false;
  }

  private requireContext(socket: WebSocket): ClientContext {
    const context = this.contexts.get(socket);

    if (!context) {
      throw new DuplicateSessionError("SOCKET_CONTEXT_MISSING", "Socket context is not available.");
    }

    return context;
  }

  private requireIdentity(socket: WebSocket): TechnicalPlayerIdentity {
    const context = this.requireContext(socket);

    if (!context.identity) {
      throw new DuplicateSessionError("AUTH_REQUIRED", "Authenticate the socket first.");
    }

    return context.identity;
  }

  private buildBaseUrl(request: IncomingMessage): string {
    if (this.config.publicBaseUrl) {
      return this.config.publicBaseUrl;
    }

    const protocol = (request.headers["x-forwarded-proto"] as string | undefined) ?? "http";
    const host = request.headers.host ?? `${this.config.host}:${this.config.port}`;
    return `${protocol}://${host}`;
  }

  private sendError(
    socket: WebSocket,
    requestId: string | undefined,
    code: string,
    message: string
  ): void {
    this.send(socket, {
      type: "error",
      requestId,
      serverTime: Date.now(),
      payload: {
        code,
        message
      }
    });
  }

  private send(socket: WebSocket, message: ServerWsMessage): void {
    if (socket.readyState !== WebSocket.OPEN) {
      return;
    }

    socket.send(JSON.stringify(message));
  }
}
