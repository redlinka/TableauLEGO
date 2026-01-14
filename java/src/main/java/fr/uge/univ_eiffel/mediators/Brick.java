package fr.uge.univ_eiffel.mediators;

public record Brick(String name, String serial, String certificate) {

    @Override
    public String toString() {
        return "Brick{name='%s', serial='%s', certificate='%s'}".formatted(name, serial, certificate);
    }

    @Override
    public boolean equals(Object obj) {
        if (this == obj) return true;
        if (obj == null || getClass() != obj.getClass()) return false;
        Brick brick = (Brick) obj;
        return name.equals(brick.name) && serial.equals(brick.serial) && certificate.equals(brick.certificate);
    }
}
