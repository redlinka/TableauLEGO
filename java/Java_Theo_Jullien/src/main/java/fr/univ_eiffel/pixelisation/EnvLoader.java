package fr.univ_eiffel.pixelisation;

import java.io.FileInputStream;
import java.io.IOException;
import java.util.Properties;

public class EnvLoader {

    private static final Properties props = new Properties();

    static {
        try {
            FileInputStream fis = new FileInputStream("/var/www/app/.env");
            props.load(fis);
        } catch (IOException e) {
            System.err.println("Impossible de charger le fichier .env : " + e.getMessage());
            System.exit(1);
        }
    }

    public static String get(String key) {
        return props.getProperty(key);
    }
}
