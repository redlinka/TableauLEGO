package fr.univ_eiffel.pixelisation;

import java.sql.Connection;
import java.sql.DriverManager;
import java.sql.SQLException;

public class MySQLConnection {
    private static final String URL = EnvLoader.get("MYSQL_URL");
    private static final String USER = EnvLoader.get("MYSQL_USER");
    private static final String PASSWORD = EnvLoader.get("MYSQL_PASSWORD");

    static {
        try {
            Class.forName("com.mysql.cj.jdbc.Driver");
        } catch (ClassNotFoundException e) {
            System.err.println("Pilote MySQL introuvable : " + e.getMessage());
            System.exit(1);
        }
    }

    public static Connection getConnection() throws SQLException {
        return DriverManager.getConnection(URL, USER, PASSWORD);
    }
}