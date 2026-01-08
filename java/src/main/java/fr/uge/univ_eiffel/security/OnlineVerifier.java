package fr.uge.univ_eiffel.security;

import fr.uge.univ_eiffel.Brick;
import fr.uge.univ_eiffel.mediators.FactoryClient;

public final class OnlineVerifier implements BrickVerifier {
    private final FactoryClient client;

    public OnlineVerifier(FactoryClient client) {
        this.client = client;
    }

    @Override
    public boolean verify(Brick brick) {
        try {
            return client.verify(brick.name(), brick.serial(), brick.certificate());
        } catch (Exception e) {
            System.err.println("Online verification failed due to network error: " + e.getMessage());
            return false;
        }
    }
}
