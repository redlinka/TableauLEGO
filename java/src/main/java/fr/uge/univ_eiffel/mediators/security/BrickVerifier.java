package fr.uge.univ_eiffel.mediators.security;

import fr.uge.univ_eiffel.mediators.Brick;

public sealed interface BrickVerifier permits OfflineVerifier, OnlineVerifier{
    boolean verify(Brick brick);
}
