import { FastifyRequest } from "fastify";
import { TechnicalPlayerIdentity } from "@games-platform/game-contracts";
import { RepositoryBundle } from "../../storage/mongoose/repositories/index.js";
import { TokenService } from "./tokenService.js";

export function extractBearerToken(request: FastifyRequest): string | null {
  const header = request.headers.authorization;

  if (!header || !header.startsWith("Bearer ")) {
    return null;
  }

  return header.slice("Bearer ".length).trim();
}

export async function resolveAuthenticatedPlayer(
  request: FastifyRequest,
  dependencies: {
    tokenService: TokenService;
    repositories: RepositoryBundle;
  }
): Promise<TechnicalPlayerIdentity | null> {
  const token = extractBearerToken(request);

  if (!token) {
    return null;
  }

  try {
    const verified = await dependencies.tokenService.verifyAccessToken(token);
    const identity = await dependencies.repositories.players.ensureTechnicalPlayer(
      verified.identity,
      {
        externalRef: verified.externalRef,
        technicalLoyaltyId: verified.technicalLoyaltyId
      }
    );
    await dependencies.repositories.players.touch(identity.playerId);
    return identity;
  } catch {
    return null;
  }
}
