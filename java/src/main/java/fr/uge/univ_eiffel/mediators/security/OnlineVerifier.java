package fr.uge.univ_eiffel.mediators.security;

import fr.uge.univ_eiffel.mediators.Brick;
import fr.uge.univ_eiffel.mediators.legofactory.LegoFactory;

public final class OnlineVerifier implements BrickVerifier {
    private final LegoFactory factory;

    public OnlineVerifier(LegoFactory factory) {
        this.factory = factory;
    }

    @Override
    public boolean verify(Brick brick) {
        try {
            return factory.verify(brick.name(), brick.serial(), brick.certificate());
        } catch (Exception e) {
            System.err.println("Online verification failed due to network error: " + e.getMessage());
            return false;
        }
    }
}
