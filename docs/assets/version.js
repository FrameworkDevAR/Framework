// The version shown in the sidebar badge, and the release it links to. This is
// the only place the documentation records it — ./framework setVersion rewrites
// this line, and docsCheck fails when it drifts from composer.json.
window.DOCS_VERSION = "0.19.0";

// Which copy of the site this is. The deploy stamps "dev" into the copy it
// publishes under /dev/, and the pages use it to tell whether a reader who
// arrived by the dev subdomain is being served the release copy by mistake.
window.DOCS_BRANCH = "main";
