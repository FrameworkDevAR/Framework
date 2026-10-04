<?php
namespace {{namespace}};

use Framework\Application;
use Framework\Provider\Mustache;
use Framework\File\Storage;

/**
 * The Template
 */
enum Template {

    case None;

{{#templates}}
    case {{name}};
{{/templates}}



    /**
     * Returns the File Name of the Template
     * @return string
     */
    public function getFileName(): string {
        return "{$this->name}.php";
    }

    /**
     * Creates a File rendering the Template
     * @param string $path
     * @param array<string,mixed> $data
     * @return bool
     */
    public function create(string $path, array $data): bool {
        return Storage::createFile($path, $this->getFileName(), $this->render($data));
    }

    /**
     * Renders the Template with the given Data
     * @param array<string,mixed> $data
     * @param array<string,string> $partials Optional.
     * @return string
     */
    public function render(array $data, array $partials = []): string {
        $relPath = match ($this) {
        {{#templates}}
            self::{{constant}} => "{{relPath}}",
        {{/templates}}
            default => "",
        };
        if ($relPath === "") {
            return "";
        }

        $path = Application::getBasePath($relPath);
        $code = Storage::readFile($path);
        return Mustache::render($code, $data, $partials);
    }

    /**
     * Deletes the File of the Template
     * @param string $path
     * @param int $deleted Optional.
     * @return bool
     */
    public function delete(string $path, int &$deleted = 0): bool {
        $result   = Storage::deleteFile($path, $this->getFileName());
        $deleted += $result ? 1 : 0;
        return $result;
    }
}
