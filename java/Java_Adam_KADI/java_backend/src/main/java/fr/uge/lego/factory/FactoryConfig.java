package fr.uge.lego.factory;

import java.util.Objects;

/**
 * Configuration for Lego factory REST API.
 */
public final class FactoryConfig {
    private final String baseUrl;
    private final String email;
    private final String secretKey;

    public FactoryConfig(String baseUrl, String email, String secretKey) {
        this.baseUrl = Objects.requireNonNull(baseUrl);
        this.email = Objects.requireNonNull(email);
        this.secretKey = Objects.requireNonNull(secretKey);
    }

    public String baseUrl() { return baseUrl; }
    public String email() { return email; }
    public String secretKey() { return secretKey; }

    public static FactoryConfig fromEnv() {
        String baseUrl = System.getenv().getOrDefault("LEGOFACTORY_BASE_URL",
                "https://legofactory.plade.org");
        String email = System.getProperty("LEGOFACTORY_EMAIL",
                System.getenv("LEGOFACTORY_EMAIL"));
        String secret = System.getProperty("LEGOFACTORY_SECRET",
                System.getenv("LEGOFACTORY_SECRET"));
        if (email == null || secret == null) {
            throw new IllegalStateException("LEGOFACTORY_EMAIL and LEGOFACTORY_SECRET must be set");
        }
        return new FactoryConfig(baseUrl, email, secret);
    }
}
