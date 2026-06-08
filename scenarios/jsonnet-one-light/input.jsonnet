local base = {
  apiVersion: "v1",
  kind: "ConfigMap",
};

local makeConfig(name, data) = base {
  metadata: { name: name },
  data: data,
};

{
  dev: makeConfig("dev-config", { LOG_LEVEL: "debug" }),
  prod: makeConfig("prod-config", { LOG_LEVEL: "warn", replicas: "3" }),
}
