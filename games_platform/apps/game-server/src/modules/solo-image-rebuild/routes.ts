import {
  AbandonSoloImageRebuildSessionResponse,
  CreateSoloImageRebuildSessionRequest,
  SoloImageRebuildResultResponse,
  SoloImageRebuildSessionResponse,
  SubmitSoloImageRebuildPlacementRequest,
  SubmitSoloImageRebuildPlacementResponse
} from "@games-platform/game-contracts";
import { FastifyInstance, FastifyReply } from "fastify";
import { z } from "zod";
import {
  PuzzleNotFoundError,
  SoloSessionNotFoundError
} from "../../domain/image-rebuild/soloSessionService.js";
import { AppServices } from "../../types.js";
import {
  buildPublicBaseUrl,
  requireAuthenticatedPlayer
} from "../common/requestContext.js";

const createSessionBodySchema = z.object({
  puzzleId: z.string().trim().min(1),
  seed: z.string().trim().min(1).optional()
});

const placementBodySchema = z.object({
  anchorX: z.number().int().min(0),
  anchorY: z.number().int().min(0),
  rotation: z.union([z.literal(0), z.literal(90), z.literal(180), z.literal(270)])
});

function sendKnownError(
  reply: FastifyReply,
  error: unknown
) {
  if (error instanceof PuzzleNotFoundError) {
    return reply.code(404).send({
      error: {
        code: "PUZZLE_NOT_FOUND",
        message: error.message
      }
    });
  }

  if (error instanceof SoloSessionNotFoundError) {
    return reply.code(404).send({
      error: {
        code: "SESSION_NOT_FOUND",
        message: error.message
      }
    });
  }

  throw error;
}

export async function registerSoloImageRebuildRoutes(
  app: FastifyInstance,
  services: AppServices
): Promise<void> {
  app.post("/api/v1/solo/image-rebuild/sessions", async (request, reply) => {
    const player = await requireAuthenticatedPlayer(request, services);

    if (!player) {
      return reply.code(401).send({
        error: {
          code: "AUTH_REQUIRED",
          message: "Authentication is required to create a solo session."
        }
      });
    }

    try {
      const body = createSessionBodySchema.parse(
        (request.body ?? {}) as CreateSoloImageRebuildSessionRequest
      );
      const state = await services.imageRebuildSoloService.createSession({
        player,
        puzzleId: body.puzzleId,
        seed: body.seed,
        baseUrl: buildPublicBaseUrl(request, services)
      });
      const response: SoloImageRebuildSessionResponse = {
        state
      };

      return reply.code(201).send(response);
    } catch (error) {
      return sendKnownError(reply, error);
    }
  });

  app.get<{ Params: { sessionId: string } }>(
    "/api/v1/solo/image-rebuild/sessions/:sessionId",
    async (request, reply) => {
      const player = await requireAuthenticatedPlayer(request, services);

      if (!player) {
        return reply.code(401).send({
          error: {
            code: "AUTH_REQUIRED",
            message: "Authentication is required to load a solo session."
          }
        });
      }

      try {
        const state = await services.imageRebuildSoloService.getSessionState({
          playerId: player.playerId,
          sessionId: request.params.sessionId,
          baseUrl: buildPublicBaseUrl(request, services)
        });
        const response: SoloImageRebuildSessionResponse = {
          state
        };

        return reply.send(response);
      } catch (error) {
        return sendKnownError(reply, error);
      }
    }
  );

  app.post<{ Params: { sessionId: string } }>(
    "/api/v1/solo/image-rebuild/sessions/:sessionId/placements",
    async (request, reply) => {
      const player = await requireAuthenticatedPlayer(request, services);

      if (!player) {
        return reply.code(401).send({
          error: {
            code: "AUTH_REQUIRED",
            message: "Authentication is required to submit a placement."
          }
        });
      }

      try {
        const body = placementBodySchema.parse(
          (request.body ?? {}) as SubmitSoloImageRebuildPlacementRequest
        );
        const response: SubmitSoloImageRebuildPlacementResponse =
          await services.imageRebuildSoloService.submitPlacement({
            player,
            sessionId: request.params.sessionId,
            baseUrl: buildPublicBaseUrl(request, services),
            anchorX: body.anchorX,
            anchorY: body.anchorY,
            rotation: body.rotation
          });

        return reply.send(response);
      } catch (error) {
        return sendKnownError(reply, error);
      }
    }
  );

  app.post<{ Params: { sessionId: string } }>(
    "/api/v1/solo/image-rebuild/sessions/:sessionId/abandon",
    async (request, reply) => {
      const player = await requireAuthenticatedPlayer(request, services);

      if (!player) {
        return reply.code(401).send({
          error: {
            code: "AUTH_REQUIRED",
            message: "Authentication is required to abandon a solo session."
          }
        });
      }

      try {
        const state = await services.imageRebuildSoloService.abandonSession({
          player,
          sessionId: request.params.sessionId,
          baseUrl: buildPublicBaseUrl(request, services)
        });
        const response: AbandonSoloImageRebuildSessionResponse = {
          state
        };

        return reply.send(response);
      } catch (error) {
        return sendKnownError(reply, error);
      }
    }
  );

  app.get<{ Params: { sessionId: string } }>(
    "/api/v1/solo/image-rebuild/sessions/:sessionId/result",
    async (request, reply) => {
      const player = await requireAuthenticatedPlayer(request, services);

      if (!player) {
        return reply.code(401).send({
          error: {
            code: "AUTH_REQUIRED",
            message: "Authentication is required to load a solo result."
          }
        });
      }

      try {
        const state = await services.imageRebuildSoloService.getResult({
          playerId: player.playerId,
          sessionId: request.params.sessionId,
          baseUrl: buildPublicBaseUrl(request, services)
        });
        const response: SoloImageRebuildResultResponse = {
          sessionId: state.session.sessionId,
          finished: state.finished,
          result: state.result,
          finishReason: state.finishReason,
          metrics: state.metrics
        };

        return reply.send(response);
      } catch (error) {
        return sendKnownError(reply, error);
      }
    }
  );
}
