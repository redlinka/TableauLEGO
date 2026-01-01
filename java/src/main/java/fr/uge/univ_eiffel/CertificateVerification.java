package fr.uge.univ_eiffel;
import fr.uge.univ_eiffel.manager.InventoryManager;

import java.nio.charset.StandardCharsets;
import java.security.*;
import java.security.spec.X509EncodedKeySpec;
import java.util.Base64;

public class CertificateVerification {

    public static boolean verify(Brick brick, String base64PublicKey) {
        try {
            byte[] nameBytes = brick.name().getBytes(StandardCharsets.US_ASCII);
            byte[] serialBytes = InventoryManager.hexToBytes(brick.serial());

            byte[] message = new byte[nameBytes.length + serialBytes.length];
            System.arraycopy(nameBytes, 0, message, 0, nameBytes.length);
            System.arraycopy(serialBytes, 0, message, nameBytes.length, serialBytes.length);

            byte[] signatureBytes = InventoryManager.hexToBytes(brick.certificate());

            byte[] keyBytes = Base64.getDecoder().decode(base64PublicKey);
            PublicKey publicKey = KeyFactory.getInstance("Ed25519")
                    .generatePublic(new X509EncodedKeySpec(keyBytes));

            Signature sig = Signature.getInstance("Ed25519");
            sig.initVerify(publicKey);
            sig.update(message);
            return sig.verify(signatureBytes);

        } catch (Exception e) {
            e.printStackTrace();
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
