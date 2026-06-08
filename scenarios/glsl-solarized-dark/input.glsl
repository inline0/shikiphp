#version 330 core

in vec3 fragNormal;
in vec2 fragUV;
out vec4 fragColor;

uniform sampler2D albedo;
uniform vec3 lightDir;
uniform float ambient;

// Lambertian shading
void main() {
    vec3 n = normalize(fragNormal);
    float diff = max(dot(n, -lightDir), 0.0);
    vec4 tex = texture(albedo, fragUV);
    vec3 color = tex.rgb * (ambient + diff);
    fragColor = vec4(color, 1.0);
}
