import { afterAll, beforeAll, describe, expect, it } from "vitest";
import { createDuplicateHarness, WsTestClient } from "./helpers/duplicateHarness.js";

describe("duplicate chat", () => {
  let harness: Awaited<ReturnType<typeof createDuplicateHarness>>;
  let hostClient: WsTestClient;
  let guestClient: WsTestClient;
  let sessionId = "";

  beforeAll(async () => {
    harness = await createDuplicateHarness();
    const hostAuth = await harness.createGuest("Host-Chat");
    const guestAuth = await harness.createGuest("Guest-Chat");
    hostClient = await harness.connectClient(hostAuth.accessToken);
    guestClient = await harness.connectClient(guestAuth.accessToken);

    hostClient.send({
      type: "room.create",
      payload: {
        gameType: "line_breaker",
        mode: "duplicate_2p"
      }
    });
    const created = await hostClient.waitFor("room.created");

    guestClient.send({
      type: "room.join",
      payload: {
        roomCode: created.payload.roomCode
      }
    });
    const joined = await guestClient.waitFor("room.joined");
    sessionId = joined.payload.sessionId;
  }, 300_000);

  afterAll(async () => {
    if (harness) {
      await harness.close();
    }
  }, 300_000);

  it("broadcasts a chat message and persists it", async () => {
    hostClient.send({
      type: "chat.send",
      requestId: "chat-ok",
      payload: {
        sessionId,
        content: "Bonjour duplicate"
      }
    });

    const message = await guestClient.waitFor(
      "room.chat_message",
      (event) => event.payload.message.content === "Bonjour duplicate"
    );

    expect(message.payload.message.playerId).toBeTruthy();

    const stored = await harness.repositories.chatMessages.listBySessionId(sessionId, 10);

    expect(stored).toHaveLength(1);
    expect(stored[0].content).toBe("Bonjour duplicate");
  }, 120_000);

  it("rejects a message that exceeds the configured length", async () => {
    hostClient.send({
      type: "chat.send",
      requestId: "chat-too-long",
      payload: {
        sessionId,
        content: "x".repeat(harness.config.duplicateChatMaxLength + 10)
      }
    });

    const error = await hostClient.waitFor(
      "error",
      (message) => message.requestId === "chat-too-long"
    );

    expect(error.payload.code).toBe("CHAT_TOO_LONG");
  }, 120_000);
});
