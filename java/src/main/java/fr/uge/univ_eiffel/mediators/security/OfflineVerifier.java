package fr.uge.univ_eiffel.mediators.security;
import fr.uge.univ_eiffel.mediators.Brick;
import fr.uge.univ_eiffel.mediators.InventoryManager;

import java.nio.charset.StandardCharsets;
import java.security.*;
import java.security.spec.X509EncodedKeySpec;
import java.util.Base64;

public final class OfflineVerifier implements BrickVerifier {
    private final String publicKey;

    public OfflineVerifier(String publicKey) {
        this.publicKey = publicKey;
    }

    /**
     * Verifies the authenticity of a brick using its digital certificate and a public key.
     *
     * This method reconstructs the signed message from the brick's name and serial number,
     * then verifies the provided certificate using the Ed25519 signature algorithm.
     * The public key is expected to be provided in Base64-encoded format.
     *
     * @param brick the Brick whose certificate is to be verified
     * @return true if the certificate is valid and the signature matches the brick data;
     *         false otherwise or if an error occurs during verification
     */

    @Override
    public boolean verify(Brick brick) {
        try {
            byte[] nameBytes = brick.name().getBytes(StandardCharsets.US_ASCII);
            byte[] serialBytes = InventoryManager.hexToBytes(brick.serial());

            byte[] message = new byte[nameBytes.length + serialBytes.length];
            System.arraycopy(nameBytes, 0, message, 0, nameBytes.length);
            System.arraycopy(serialBytes, 0, message, nameBytes.length, serialBytes.length);

            byte[] signatureBytes = InventoryManager.hexToBytes(brick.certificate());

            byte[] keyBytes = Base64.getDecoder().decode(publicKey);
            PublicKey publicKey = KeyFactory.getInstance("Ed25519")
                    .generatePublic(new X509EncodedKeySpec(keyBytes));

            Signature sig = Signature.getInstance("Ed25519");
            sig.initVerify(publicKey);
            sig.update(message);
            return sig.verify(signatureBytes);

        } catch (Exception e) {
            // e.printStackTrace();
            return false;
        }
    }

//    public static void main(String[] args) throws IOException {
//        Brick b = new Brick("1-1/c91a09",
//                "251563191d9df8e3861f113f1896bf",
//                "fad4eccaa9e245fbbbeabcf04bf103c17c9f09b5092ed5f66b74463ca92b0066c18181113df9b54da9de64b73467f3646436671adee72bf50b1ac9873c63300c");
//        FactoryClient c = FactoryClient.makeFromProps("config.properties");
//
//        if(!verify(b, c.signaturePublicKey()) && !c.verify(b.name(), b.serial(),b.certificate())){
//            System.err.println("Vérification de signature échouée");
//        }
//
//    }
}
