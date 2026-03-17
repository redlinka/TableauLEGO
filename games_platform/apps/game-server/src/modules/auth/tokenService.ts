import { createHmac } from "node:crypto";
import { AuthSource, TechnicalPlayerIdentity } from "@games-platform/game-contracts";
import { jwtVerify, SignJWT } from "jose";
import { AppConfig } from "../../config/env.js";

const encoder = new TextEncoder();

export interface VerifiedToken {
  identity: TechnicalPlayerIdentity;
  issuer: string;
  audience: string;
  expiresAt?: string;
  externalRef?: {
    issuer: string;
    subject: string;
  };
  technicalLoyaltyId?: string;
  subjectHash?: string;
}

export class TokenService {
  constructor(private readonly config: AppConfig) {}

  async issuePlayerToken(
    identity: TechnicalPlayerIdentity,
    options?: {
      technicalLoyaltyId?: string;
    }
  ): Promise<{
    accessToken: string;
    expiresAt: string;
  }> {
    const expiresAtDate = new Date(
      Date.now() + this.config.guestTokenTtlSeconds * 1_000
    );

    const accessToken = await new SignJWT({
      src: identity.authSource,
      alias: identity.publicAlias,
      tlid: options?.technicalLoyaltyId
    })
      .setProtectedHeader({ alg: "HS256" })
      .setIssuedAt()
      .setIssuer(this.config.tokenIssuer)
      .setAudience(this.config.tokenAudience)
      .setSubject(identity.playerId)
      .setExpirationTime(Math.floor(expiresAtDate.getTime() / 1_000))
      .sign(encoder.encode(this.config.gameTokenSecret));

    return {
      accessToken,
      expiresAt: expiresAtDate.toISOString()
    };
  }

  async issueGuestToken(identity: TechnicalPlayerIdentity): Promise<{
    accessToken: string;
    expiresAt: string;
  }> {
    return this.issuePlayerToken(identity);
  }

  async exchangePhpToken(token: string): Promise<VerifiedToken & {
    accessToken: string;
  }> {
    const verified = await this.verifyPhpBridgeToken(token);

    if (!verified) {
      throw new Error("Invalid PHP bridge token");
    }

    const issued = await this.issuePlayerToken(verified.identity, {
      technicalLoyaltyId: verified.technicalLoyaltyId
    });

    return {
      ...verified,
      accessToken: issued.accessToken,
      expiresAt: issued.expiresAt
    };
  }

  async verifyAccessToken(token: string): Promise<VerifiedToken> {
    const gameVerification = await this.tryVerifyGameToken(token, {
      secret: this.config.gameTokenSecret,
      issuer: this.config.tokenIssuer,
      audience: this.config.tokenAudience,
      fallbackSource: AuthSource.Guest
    });

    if (gameVerification) {
      return gameVerification;
    }

    const phpVerification = await this.verifyPhpBridgeToken(token);

    if (phpVerification) {
      return phpVerification;
    }

    throw new Error("Invalid access token");
  }

  private async tryVerifyGameToken(
    token: string,
    options: {
      secret: string;
      issuer: string;
      audience: string;
      fallbackSource: AuthSource;
    }
  ): Promise<VerifiedToken | null> {
    try {
      const { payload } = await jwtVerify(token, encoder.encode(options.secret), {
        issuer: options.issuer,
        audience: options.audience
      });

      const playerId = typeof payload.sub === "string" ? payload.sub : "";
      const publicAlias =
        typeof payload.alias === "string" && payload.alias.trim()
          ? payload.alias
          : `${options.fallbackSource === AuthSource.Php ? "Member" : "Guest"}-${playerId.slice(-6)}`;
      const authSource =
        payload.src === AuthSource.Php || payload.src === AuthSource.Guest
          ? payload.src
          : options.fallbackSource;

      if (!playerId) {
        return null;
      }

      return {
        identity: {
          playerId,
          authSource,
          publicAlias
        },
        issuer: options.issuer,
        audience: options.audience,
        technicalLoyaltyId:
          typeof payload.tlid === "string" ? payload.tlid : undefined,
        expiresAt:
          typeof payload.exp === "number"
            ? new Date(payload.exp * 1_000).toISOString()
            : undefined
      };
    } catch {
      return null;
    }
  }

  private async verifyPhpBridgeToken(token: string): Promise<VerifiedToken | null> {
    if (!this.config.phpGameTokenSecret) {
      return null;
    }

    try {
      const { payload } = await jwtVerify(token, encoder.encode(this.config.phpGameTokenSecret), {
        issuer: this.config.phpTokenIssuer,
        audience: this.config.phpTokenAudience
      });
      const phpSubject = typeof payload.sub === "string" ? payload.sub.trim() : "";

      if (!phpSubject) {
        return null;
      }

      const subjectHash = this.hashPhpIdentity(this.config.phpTokenIssuer, phpSubject);
      const loyaltyClaim = this.getPhpLoyaltyClaim(payload);
      const technicalLoyaltyId = loyaltyClaim
        ? `loy_${this.hashPhpIdentity(this.config.phpTokenIssuer, loyaltyClaim).slice(0, 24)}`
        : undefined;

      return {
        identity: {
          playerId: `php_${subjectHash.slice(0, 24)}`,
          authSource: AuthSource.Php,
          publicAlias:
            typeof payload.alias === "string" && payload.alias.trim()
              ? payload.alias.trim().slice(0, 24)
              : `Member-${subjectHash.slice(0, 6).toUpperCase()}`
        },
        issuer: this.config.phpTokenIssuer,
        audience: this.config.phpTokenAudience,
        expiresAt:
          typeof payload.exp === "number"
            ? new Date(payload.exp * 1_000).toISOString()
            : undefined,
        externalRef: {
          issuer: this.config.phpTokenIssuer,
          subject: subjectHash
        },
        technicalLoyaltyId,
        subjectHash
      };
    } catch {
      return null;
    }
  }

  private hashPhpIdentity(issuer: string, subject: string): string {
    return createHmac("sha256", this.config.gameTokenSecret)
      .update(`${issuer}:${subject}`)
      .digest("hex");
  }

  private getPhpLoyaltyClaim(payload: Record<string, unknown>): string | undefined {
    const direct =
      typeof payload.loyaltyId === "string" && payload.loyaltyId.trim()
        ? payload.loyaltyId.trim()
        : undefined;

    if (direct) {
      return direct;
    }

    return typeof payload.loy === "string" && payload.loy.trim()
      ? payload.loy.trim()
      : undefined;
  }
}
