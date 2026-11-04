export default function (pi) {
  pi.on("session_start", (_event, ctx) => {
    const setup = ctx.sessionManager.getEntries().find(
      entry => entry.type === "custom" && entry.customType === "test:session-setup",
    );
    pi.appendEntry("test:setup-observed", setup?.data ?? "missing");
  });
}
