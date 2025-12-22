package fr.uge.lego.image;

import java.awt.image.BufferedImage;

/**
 * Strategy interface used to change the resolution of an image.
 */
@FunctionalInterface
public interface ResolutionChanger {
    void convert(BufferedImage source, BufferedImage destination);
}
