package fr.uge.univ_eiffel;

import fr.uge.univ_eiffel.image_processing.downscalers.BicubicInterpolator;
import fr.uge.univ_eiffel.image_processing.downscalers.NearestNeighbour;

public class Main {
    public static void main(String[] args) throws Exception {

        App app = App.initialize("config.properties");
        app.run("mcdo.png", new NearestNeighbour(), "downscaled",256,256,1, 1000000);
    }
}

