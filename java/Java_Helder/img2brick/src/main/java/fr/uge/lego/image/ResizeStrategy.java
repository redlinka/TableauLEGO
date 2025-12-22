package fr.uge.lego.image;

import java.awt.image.BufferedImage;

public interface ResizeStrategy {

	BufferedImage resize(BufferedImage input, int newWidth, int newHeight);

}
