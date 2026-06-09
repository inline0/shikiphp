cbuffer Constants : register(b0)
{
    float4x4 worldViewProj;
    float time;
};

struct VSInput
{
    float3 position : POSITION;
    float2 uv : TEXCOORD0;
};

float4 main(VSInput input) : SV_POSITION
{
    float wobble = sin(time + input.position.x) * 0.1;
    float4 pos = float4(input.position, 1.0);
    pos.y += wobble;
    return mul(pos, worldViewProj);
}
