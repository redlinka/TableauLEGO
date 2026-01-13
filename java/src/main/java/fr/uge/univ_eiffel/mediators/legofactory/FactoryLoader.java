package fr.uge.univ_eiffel.mediators.legofactory;

import java.io.IOException;
import java.io.InputStream;
import java.util.Properties;

public class FactoryLoader {

    /** * Loads config and returns an implementation of LegoFactory.
     * Currently returns the Http implementation, but could be changed to return others.
     */
    public static LegoFactory loadFromProps(String fileName) {
        Properties props = new Properties();

        try (InputStream input = FactoryLoader.class.getClassLoader().getResourceAsStream(fileName)) {
            if (input == null) {
                throw new RuntimeException("Properties file '" + fileName + "' not found.");
            }
            props.load(input);

            String url = props.getProperty("API_URL");
            String email = props.getProperty("USER_MAIL");
            String key = props.getProperty("API_KEY");

            if (email == null || key == null) {
                throw new RuntimeException("Config missing in " + fileName);
            }

            //We decide here which implementation to use.
            return new HttpFactoryClient(url, email, key);

        } catch (IOException e) {
            throw new RuntimeException(e);
        }
    }
}