import { ClientWsMessage, ServerWsMessage } from "@games-platform/game-contracts";

const DEFAULT_WS_URL = import.meta.env.VITE_WS_URL ?? "ws://127.0.0.1:3001/ws";

export class GameWebSocketClient {
  private socket: WebSocket | null = null;

  connect(options: {
    url?: string;
    token?: string;
    onMessage: (message: ServerWsMessage) => void;
    onOpen?: () => void;
    onClose?: () => void;
    onError?: (event: Event) => void;
  }): void {
    this.disconnect();
    this.socket = new WebSocket(options.url ?? DEFAULT_WS_URL);

    this.socket.addEventListener("open", () => {
      options.onOpen?.();

      if (options.token) {
        this.send({
          type: "session.authenticate",
          requestId: crypto.randomUUID(),
          payload: {
            token: options.token
          }
        });
      }
    });

    this.socket.addEventListener("message", (event) => {
      const parsed = JSON.parse(event.data) as ServerWsMessage;
      options.onMessage(parsed);
    });

    this.socket.addEventListener("close", () => {
      options.onClose?.();
    });

    this.socket.addEventListener("error", (event) => {
      options.onError?.(event);
    });
  }

  send(message: ClientWsMessage): void {
    if (!this.socket || this.socket.readyState !== WebSocket.OPEN) {
      return;
    }

    this.socket.send(JSON.stringify(message));
  }

  disconnect(): void {
    if (!this.socket) {
      return;
    }

    this.socket.close();
    this.socket = null;
  }

  get readyState(): number | undefined {
    return this.socket?.readyState;
  }
}

