<?php

namespace App\Chronicle;

use App\Models\Chronicle;
use App\Models\ChronicleLocation;
use App\Models\Scene;
use App\Models\SceneContext;
use App\Models\User;
use App\Scene\SceneContextService;
use App\World\MixedChronicleException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

final class ChronicleLocationService
{
    public function __construct(private SceneContextService $contexts) {}

    /**
     * @return Collection<int, ChronicleLocation>
     */
    public function listForChronicle(Chronicle $chronicle): Collection
    {
        return ChronicleLocation::query()
            ->where('chronicle_id', $chronicle->id)
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  array{name: string, description?: ?string, environment_details?: ?string, scene_id?: ?int}  $data
     */
    public function create(Chronicle $chronicle, array $data): ChronicleLocation
    {
        $description = $this->nullableText($data['description'] ?? null);
        $environmentDetails = $this->nullableText($data['environment_details'] ?? null);
        $sceneContext = null;

        if (! empty($data['scene_id'])) {
            $scene = Scene::query()->findOrFail((int) $data['scene_id']);
            $this->assertSceneInChronicle($scene, $chronicle);

            $sceneContext = SceneContext::query()->where('scene_id', $scene->id)->first();
            if ($description === null) {
                $description = $sceneContext?->situation;
            }
            if ($environmentDetails === null) {
                $environmentDetails = $sceneContext?->environment_details;
            }
        }

        $location = ChronicleLocation::query()->create([
            'chronicle_id' => $chronicle->id,
            'name' => trim($data['name']),
            'description' => $description,
            'environment_details' => $environmentDetails,
            'image_path' => null,
        ]);

        $imagePath = $this->copySceneImageForLocation($location, $sceneContext?->location_image_path);
        if ($imagePath !== null) {
            $location->image_path = $imagePath;
            $location->save();
        }

        return $location->refresh();
    }

    /**
     * @param  array{name?: string, description?: ?string, environment_details?: ?string}  $data
     */
    public function update(ChronicleLocation $location, array $data): ChronicleLocation
    {
        if (array_key_exists('name', $data)) {
            $location->name = trim((string) $data['name']);
        }

        if (array_key_exists('description', $data)) {
            $location->description = $this->nullableText($data['description']);
        }

        if (array_key_exists('environment_details', $data)) {
            $location->environment_details = $this->nullableText($data['environment_details']);
        }

        $location->save();

        return $location->refresh();
    }

    public function delete(ChronicleLocation $location): void
    {
        $this->deleteImageFile($location->image_path);
        $location->delete();
    }

    public function applyToScene(
        Scene $scene,
        ChronicleLocation $location,
        int $expectedRevision,
        ?User $updatedBy,
    ): SceneContext {
        $scene->loadMissing('gameSession');
        if ((int) $location->chronicle_id !== (int) $scene->gameSession->chronicle_id) {
            throw new MixedChronicleException;
        }

        return $this->contexts->apply($scene, [
            'situation' => $location->description,
            'environment_details' => $location->environment_details,
            'location_image_path' => $location->image_path,
        ], $expectedRevision, $updatedBy);
    }

    public function storeImage(ChronicleLocation $location, UploadedFile $file): string
    {
        $disk = Storage::disk('public');
        $extension = strtolower($file->getClientOriginalExtension());
        $directory = 'chronicle-locations/'.$location->id;
        $filename = 'location.'.$extension;
        $relativePath = $directory.'/'.$filename;

        if ($location->image_path !== null && $disk->exists($location->image_path)) {
            $disk->delete($location->image_path);
        }

        $disk->putFileAs($directory, $file, $filename);

        $location->image_path = $relativePath;
        $location->save();

        return $relativePath;
    }

    private function assertSceneInChronicle(Scene $scene, Chronicle $chronicle): void
    {
        $scene->loadMissing('gameSession');
        if ((int) $scene->gameSession->chronicle_id !== (int) $chronicle->id) {
            throw new MixedChronicleException;
        }
    }

    private function nullableText(mixed $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $text = trim((string) $raw);

        return $text === '' ? null : $text;
    }

    private function copySceneImageForLocation(ChronicleLocation $location, ?string $sourcePath): ?string
    {
        if ($sourcePath === null || $sourcePath === '') {
            return null;
        }

        $disk = Storage::disk('public');
        if (! $disk->exists($sourcePath)) {
            return null;
        }

        $extension = pathinfo($sourcePath, PATHINFO_EXTENSION);
        if ($extension === '') {
            $extension = 'png';
        }

        $directory = 'chronicle-locations/'.$location->id;
        $filename = 'location.'.strtolower($extension);
        $targetPath = $directory.'/'.$filename;

        $disk->makeDirectory($directory);
        $disk->copy($sourcePath, $targetPath);

        return $targetPath;
    }

    private function deleteImageFile(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        $disk = Storage::disk('public');
        if ($disk->exists($path)) {
            $disk->delete($path);
        }
    }
}
