struct VertexOutput {
  @builtin(position) pos: vec4<f32>,
  @location(0) color: vec3<f32>,
};

@vertex
fn vs_main(@location(0) in_pos: vec3<f32>) -> VertexOutput {
  var out: VertexOutput;
  out.pos = vec4<f32>(in_pos, 1.0);
  out.color = vec3<f32>(1.0, 0.5, 0.25);
  return out;
}

@fragment
fn fs_main(in: VertexOutput) -> @location(0) vec4<f32> {
  return vec4<f32>(in.color, 1.0);
}
