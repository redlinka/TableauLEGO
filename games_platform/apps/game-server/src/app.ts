import path from "node:path";
import cors from "@fastify/cors";
import fastify from "fastify";
import fastifyStatic from "@fastify/static";
import { ZodError } from "zod";
import { registerAuthRoutes } from "./modules/auth/routes.js";
import { registerBootstrapRoutes } from "./modules/bootstrap/routes.js";
import { registerHealthRoutes } from "./modules/health/routes.js";
import { registerHistoryRoutes } from "./modules/history/routes.js";
import { registerLoyaltyRoutes } from "./modules/loyalty/routes.js";
import { registerPuzzleRoutes } from "./modules/puzzles/routes.js";
import { registerSoloImageRebuildRoutes } from "./modules/solo-image-rebuild/routes.js";
import { registerSoloLineBreakerRoutes } from "./modules/solo-line-breaker/routes.js";
import { AppServices } from "./types.js";

export async function buildApp(services: AppServices) {
  const app = fastify({
    logger: {
      level: services.config.nodeEnv === "production" ? "info" : "debug"
    }
  });

  await app.register(cors, {
    origin: services.config.corsOrigin.split(",").map((value) => value.trim())
  });

  await app.register(fastifyStatic, {
    root: path.resolve(services.puzzleCatalog.previewsDirectory),
    prefix: services.config.staticPreviewPrefix,
    decorateReply: false
  });

  await registerHealthRoutes(app, services);
  await registerAuthRoutes(app, services);
  await registerBootstrapRoutes(app, services);
  await registerPuzzleRoutes(app, services);
  await registerHistoryRoutes(app, services);
  await registerLoyaltyRoutes(app, services);
  await registerSoloImageRebuildRoutes(app, services);
  await registerSoloLineBreakerRoutes(app, services);

  app.setErrorHandler((error, _request, reply) => {
    if (error instanceof ZodError) {
      reply.code(400).send({
        error: {
          code: "VALIDATION_ERROR",
          message: "Request validation failed.",
          details: error.flatten()
        }
      });
      return;
    }

    app.log.error(error);
    reply.code(500).send({
      error: {
        code: "INTERNAL_ERROR",
        message: "Unexpected server error."
      }
    });
  });

  return app;
}
