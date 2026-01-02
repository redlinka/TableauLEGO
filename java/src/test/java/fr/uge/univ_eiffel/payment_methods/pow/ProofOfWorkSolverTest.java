package fr.uge.univ_eiffel.payment_methods.pow;

import org.junit.jupiter.api.BeforeEach;
import org.junit.jupiter.api.Test;

import java.security.MessageDigest;
import java.util.Arrays;
import java.util.HexFormat;

import static org.junit.jupiter.api.Assertions.*;

class ProofOfWorkSolverTest {

    private ProofOfWorkSolver solver;

    @BeforeEach
    void setUp() {
        solver = new ProofOfWorkSolver("SHA-256");
    }

    @Test
    void incrementByteArray_ShouldHandleCarryOver() {
        // Test 1: Simple increment
        byte[] data = {0, 0, 1};
        ProofOfWorkSolver.incrementByteArray(data);
        assertArrayEquals(new byte[]{0, 0, 2}, data, "Should increment last byte");

        // Test 2: Overflow (Carry)
        // [0, 255] -> should become [1, 0]
        byte[] overflow = {0, (byte)0xFF};
        ProofOfWorkSolver.incrementByteArray(overflow);
        assertArrayEquals(new byte[]{1, 0}, overflow, "Should carry over 0xFF to next byte");
    }

    @Test
    void solve_ShouldFindMatchingHash() throws Exception {
        // ARRANGE
        // We want a result whose SHA-256 hash starts with "AB" (0xAB)
        byte[] dataPrefix = new byte[]{1, 2, 3};
        byte[] hashPrefix = new byte[]{(byte)0xAB}; // Target prefix

        // ACT
        // This might take a few milliseconds
        byte[] solution = solver.solve(dataPrefix, hashPrefix);

        // ASSERT
        // We verify the solution manually: Hash the solution and check if it starts with 0xAB
        MessageDigest digest = MessageDigest.getInstance("SHA-256");
        byte[] hash = digest.digest(solution);

        assertEquals(hashPrefix[0], hash[0], "The solved hash must start with the requested prefix");

        // Ensure the solution actually starts with our data prefix
        byte[] startOfSol = Arrays.copyOf(solution, dataPrefix.length);
        assertArrayEquals(dataPrefix, startOfSol, "Solution must preserve the data prefix");
    }
}