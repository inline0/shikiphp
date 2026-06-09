module rounded_box(size, r) {
    minkowski() {
        cube([size[0] - 2*r, size[1] - 2*r, size[2]/2]);
        cylinder(r = r, h = size[2]/2);
    }
}

for (i = [0 : 2]) {
    translate([i * 30, 0, 0])
        rounded_box([20, 20, 10], r = 2);
}

difference() {
    sphere(r = 15, $fn = 64);
    cube(10, center = true);
}
