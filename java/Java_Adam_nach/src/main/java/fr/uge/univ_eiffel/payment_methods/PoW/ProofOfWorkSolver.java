package fr.uge.univ_eiffel.payment_methods.PoW;

import java.security.MessageDigest;
import java.security.NoSuchAlgorithmException;
import java.util.Arrays;

/** Solve a PoW challenge */
public class ProofOfWorkSolver {
    private final MessageDigest messageDigest;

    public ProofOfWorkSolver(String hashAlgorithm) {
        try {
            this.messageDigest = MessageDigest.getInstance(hashAlgorithm);
        } catch (NoSuchAlgorithmException e) {
            throw new RuntimeException(e);
        }
    }

    public static void incrementByteArray(byte[] data) {
        for (int i = 0; i < data.length; i++) {
            byte value = data[data.length-1-i];
            if (value == -1)
                data[data.length-1-i] = 0;
            else {
                data[data.length-1-i]++;
                break;
            }
        }
    }

    public byte[] solve(byte[] dataPrefix, byte[] hashPrefix) {
        byte[] content = Arrays.copyOf(dataPrefix, dataPrefix.length+16);
        while (true) {
            messageDigest.reset();
            byte[] digest = messageDigest.digest(content);
            if (Arrays.mismatch(digest, hashPrefix) == hashPrefix.length)
                return content;
            incrementByteArray(content);
        }
    }    
}
