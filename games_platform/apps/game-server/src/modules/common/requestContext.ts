import { FastifyRequest } from "fastify";
import { AppServices } from "../../types.js";
import { resolveAuthenticatedPlayer } from "../auth/requestAuth.js";

export function buildPublicBaseUrl(
  request: {
    headers: Record<string, unknown>;
    protocol: string;
  },
  services: Pick<AppServices, "config">
): string {
  if (services.config.publicBaseUrl) {
    return services.config.publicBaseUrl;
  }

  const host = typeof request.headers.host === "string"
    ? request.headers.host
    : `${services.config.host}:${services.config.port}`;

  return `${request.protocol}://${host}`;
}

export async function requireAuthenticatedPlayer(
  request: FastifyRequest,
  services: Pick<AppServices, "repositories" | "tokenService">
) {
  return resolveAuthenticatedPlayer(request, services);
}
