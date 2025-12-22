package fr.uge.lego.factory.pow;

import fr.uge.lego.factory.dto.Challenge;

import java.security.MessageDigest;
import java.security.NoSuchAlgorithmException;
import java.util.Arrays;

/**
 * Solver for billing proof-of-work using SHA-256.
 */
public final class ProofOfWorkSolver {

    private static byte[] hexToBytes(String hex) {
        int len = hex.length();
        if (len % 2 != 0) {
            throw new IllegalArgumentException("Invalid hex length");
        }
        byte[] out = new byte[len / 2];
        for (int i = 0; i < len; i += 2) {
            out[i / 2] = (byte) Integer.parseInt(hex.substring(i, i + 2), 16);
        }
        return out;
    }

    private static String bytesToHex(byte[] data) {
        StringBuilder sb = new StringBuilder(data.length * 2);
        for (byte b : data) {
            sb.append(String.format("%02X", b));
        }
        return sb.toString();
    }

    public byte[] solve(Challenge challenge) throws NoSuchAlgorithmException {
        byte[] prefix = hexToBytes(challenge.data_prefix);
        byte[] target = hexToBytes(challenge.hash_prefix);

        MessageDigest digest = MessageDigest.getInstance("SHA-256");
        byte[] candidate = Arrays.copyOf(prefix, prefix.length + 8);
        long counter = 0;

        while (true) {
            for (int i = 0; i < 8; i++) {
                candidate[prefix.length + i] = (byte) ((counter >>> (56 - 8 * i)) & 0xFF);
            }
            byte[] hash = digest.digest(candidate);

            boolean ok = true;
            for (int i = 0; i < target.length; i++) {
                if (hash[i] != target[i]) {
                    ok = false;
                    break;
                }
            }
            if (ok) {
                System.out.println("Found solution after " + counter + " attempts");
                return candidate;
            }
            counter++;
        }
    }

    public static String toHex(byte[] data) {
        return bytesToHex(data);
    }
}
