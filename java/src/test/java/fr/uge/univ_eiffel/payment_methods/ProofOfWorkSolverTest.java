package fr.uge.univ_eiffel.payment_methods;

import fr.uge.univ_eiffel.mediators.payment_methods.ProofOfWorkSolver;
import org.junit.jupiter.api.BeforeEach;
import org.junit.jupiter.api.Test;

import java.security.MessageDigest;
import java.util.Arrays;

import static org.junit.jupiter.api.Assertions.*;

class ProofOfWorkSolverTest {

    private ProofOfWorkSolver solver;

    @BeforeEach
    void setUp() {
        solver = new ProofOfWorkSolver("SHA-256");
    }

    @Test
    void incrementByteArray_ShouldHandleSimpleIncrement() {
        // [0, 0, 1] -> [0, 0, 2]
        byte[] data = {0, 0, 1};
        ProofOfWorkSolver.incrementByteArray(data);
        assertArrayEquals(new byte[]{0, 0, 2}, data, "Should increment the last byte");
    }

    @Test
    void incrementByteArray_ShouldHandleCarryOver() {
        // [0, 255] -> [1, 0]
        // In Java, byte 255 is represented as -1
        byte[] data = {0, (byte) 0xFF};
        ProofOfWorkSolver.incrementByteArray(data);

        assertArrayEquals(new byte[]{1, 0}, data, "Should carry over to the next byte");
    }

    @Test
    void solve_ShouldFindCorrectPreimage() throws Exception {
        // ARRANGE
        // We want a result where SHA256(result) starts with byte 0xAA
        byte[] dataPrefix = {1, 2, 3};
        byte[] targetHashPrefix = {(byte) 0xAA};

        // ACT
        byte[] solution = solver.solve(dataPrefix, targetHashPrefix);

        // ASSERT
        // 1. Verify the solution starts with our data prefix
        byte[] prefixPart = Arrays.copyOf(solution, dataPrefix.length);
        assertArrayEquals(dataPrefix, prefixPart, "Solution must start with the data prefix");

        // 2. Verify the hash actually matches
        MessageDigest digest = MessageDigest.getInstance("SHA-256");
        byte[] hash = digest.digest(solution);

        assertEquals(targetHashPrefix[0], hash[0], "The hash of the solution must start with the target prefix");
    }
}