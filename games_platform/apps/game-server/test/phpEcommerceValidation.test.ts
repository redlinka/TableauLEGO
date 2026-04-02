import { readFileSync, existsSync } from "node:fs";
import path from "node:path";
import { describe, expect, it } from "vitest";

const PHP_ROOT = path.resolve(
  import.meta.dirname,
  "../../../../Php/img2brick_final"
);

function readPhp(relativePath: string): string {
  const fullPath = path.join(PHP_ROOT, relativePath);

  if (!existsSync(fullPath)) {
    throw new Error(`PHP file not found: ${fullPath}`);
  }

  return readFileSync(fullPath, "utf-8");
}

function phpFileExists(relativePath: string): boolean {
  return existsSync(path.join(PHP_ROOT, relativePath));
}

describe("PHP e-commerce site validation", () => {
  describe("file structure", () => {
    const requiredFiles = [
      "index.php",
      "connexion.php",
      "creation.php",
      "cart.php",
      "order.php",
      "order_completed.php",
      "my_account.php",
      "my_orders.php",
      "mes_points.php",
      "logout.php",
      "config/cnx.php",
      "config/session.php",
      "config/games_api.php",
      "includes/navbar.php",
      "includes/footer.php",
      "api/coupon.php",
      "api/loyalty_policy.php"
    ];

    for (const file of requiredFiles) {
      it(`contains required file: ${file}`, () => {
        expect(phpFileExists(file)).toBe(true);
      });
    }
  });

  describe("SQL injection prevention", () => {
    const filesToCheck = [
      "connexion.php",
      "creation.php",
      "cart.php",
      "order.php",
      "my_account.php",
      "my_orders.php",
      "add_cart.php"
    ];

    for (const file of filesToCheck) {
      it(`${file} uses prepared statements (no string concatenation in SQL)`, () => {
        if (!phpFileExists(file)) {
          return;
        }

        const content = readPhp(file);
        const hasQuery = content.includes("->query(") && content.includes("$_");
        const hasPrepare = content.includes("->prepare(");

        if (content.includes("SELECT") || content.includes("INSERT") || content.includes("UPDATE")) {
          expect(hasPrepare).toBe(true);
        }

        expect(hasQuery).toBe(false);
      });
    }
  });

  describe("CSRF protection", () => {
    const formFiles = [
      "connexion.php",
      "creation.php",
      "order.php",
      "my_account.php",
      "password_forgotten.php",
      "reset_password.php"
    ];

    for (const file of formFiles) {
      it(`${file} includes CSRF token in forms`, () => {
        if (!phpFileExists(file)) {
          return;
        }

        const content = readPhp(file);

        if (content.includes('<form') && content.includes('POST')) {
          expect(
            content.includes("csrf_get()") || content.includes("csrf_validate")
          ).toBe(true);
        }
      });
    }
  });

  describe("session security configuration", () => {
    it("session.php sets HttpOnly flag", () => {
      const content = readPhp("config/session.php");

      expect(content).toContain("httponly");
    });

    it("session.php sets SameSite policy", () => {
      const content = readPhp("config/session.php");

      expect(content.toLowerCase()).toContain("samesite");
    });
  });

  describe("password security", () => {
    it("creation.php uses password_hash for storage", () => {
      const content = readPhp("creation.php");

      expect(content).toContain("password_hash");
    });

    it("connexion.php uses password_verify for comparison", () => {
      const content = readPhp("connexion.php");

      expect(content).toContain("password_verify");
    });

    it("creation.php enforces password complexity", () => {
      const content = readPhp("creation.php");

      expect(content).toContain("strlen");
      expect(content.includes("preg_match") || content.includes("ctype_")).toBe(true);
    });
  });

  describe("XSS prevention", () => {
    const outputFiles = [
      "cart.php",
      "my_account.php",
      "my_orders.php",
      "mes_points.php"
    ];

    for (const file of outputFiles) {
      it(`${file} uses htmlspecialchars for output encoding`, () => {
        if (!phpFileExists(file)) {
          return;
        }

        const content = readPhp(file);

        if (content.includes("echo") || content.includes("<?=")) {
          expect(content).toContain("htmlspecialchars");
        }
      });
    }
  });

  describe("games platform integration", () => {
    it("games_api.php generates JWT with correct structure", () => {
      const content = readPhp("config/games_api.php");

      expect(content).toContain("HS256");
      expect(content).toContain("iss");
      expect(content).toContain("aud");
      expect(content).toContain("sub");
      expect(content).toContain("exp");
      expect(content).toContain("hash_hmac");
    });

    it("games_api.php validates HTTPS in production", () => {
      const content = readPhp("config/games_api.php");

      expect(content).toContain("https://");
      expect(content).toContain("games_validate_url");
    });

    it("games_api.php caches tokens with TTL", () => {
      const content = readPhp("config/games_api.php");

      expect(content).toContain("games_token_expires");
      expect(content).toContain("time()");
    });

    it("mes_points.php displays loyalty balance", () => {
      const content = readPhp("mes_points.php");

      expect(content).toContain("games_get_balance");
    });

    it("cart.php integrates loyalty redemption", () => {
      const content = readPhp("cart.php");

      expect(
        content.includes("loyalty") || content.includes("games_get_balance")
      ).toBe(true);
    });
  });

  describe("loyalty policy API endpoint", () => {
    it("loyalty_policy.php returns JSON content type", () => {
      const content = readPhp("api/loyalty_policy.php");

      expect(content).toContain("Content-Type: application/json");
    });

    it("loyalty_policy.php includes temporal rules", () => {
      const content = readPhp("api/loyalty_policy.php");

      expect(content).toContain("temporalRules");
      expect(content).toContain("multiplier");
      expect(content).toContain("happy_hour");
      expect(content).toContain("weekend_bonus");
    });

    it("loyalty_policy.php includes expiration configuration", () => {
      const content = readPhp("api/loyalty_policy.php");

      expect(content).toContain("defaultDays");
      expect(content).toContain("expiration");
    });

    it("loyalty_policy.php includes CORS headers", () => {
      const content = readPhp("api/loyalty_policy.php");

      expect(content).toContain("Access-Control-Allow-Origin");
    });

    it("loyalty_policy.php computes current multiplier dynamically", () => {
      const content = readPhp("api/loyalty_policy.php");

      expect(content).toContain("date('G')");
      expect(content).toContain("date('N')");
      expect(content).toContain("currentMultiplier");
    });
  });

  describe("database schema (SQL dump)", () => {
    it("dump.sql exists", () => {
      const dumpPath = path.resolve(PHP_ROOT, "../../DB/dump.sql");

      expect(existsSync(dumpPath)).toBe(true);
    });

    it("dump.sql contains required tables", () => {
      const dumpPath = path.resolve(PHP_ROOT, "../../DB/dump.sql");
      const content = readFileSync(dumpPath, "utf-8");
      const requiredTables = [
        "USER",
        "IMAGE",
        "TILLING",
        "ORDER_BILL",
        "ADDRESS",
        "CATALOG",
        "INVENTORY",
        "LOG"
      ];

      for (const table of requiredTables) {
        expect(content).toContain(table);
      }
    });

    it("dump.sql uses foreign keys for referential integrity", () => {
      const dumpPath = path.resolve(PHP_ROOT, "../../DB/dump.sql");
      const content = readFileSync(dumpPath, "utf-8");

      expect(content).toContain("FOREIGN KEY");
      expect(content).toContain("REFERENCES");
    });
  });
});
