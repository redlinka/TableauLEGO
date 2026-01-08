package fr.uge.univ_eiffel.security;

import fr.uge.univ_eiffel.Brick;

public sealed interface BrickVerifier permits OfflineVerifier, OnlineVerifier{
    boolean verify(Brick brick);
}
