<?php

namespace App\Character;

use App\Character\Exceptions\InvalidCharacterInputException;
use App\Models\Character;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

final class CharacterAvatarService
{
    private const SIZE = 400;

    public function upload(Character $character, UploadedFile $file): Character
    {
        $jpeg = $this->squareJpeg($file);
        $relativePath = 'avatars/'.$character->id.'.jpg';
        $disk = Storage::disk('public');

        if (
            $character->avatar_path !== null
            && $character->avatar_path !== $relativePath
            && $disk->exists($character->avatar_path)
        ) {
            $disk->delete($character->avatar_path);
        }

        $disk->put($relativePath, $jpeg);

        $character->avatar_path = $relativePath;
        $character->save();

        return $character;
    }

    private function squareJpeg(UploadedFile $file): string
    {
        $contents = file_get_contents($file->getRealPath());
        if ($contents === false) {
            throw new InvalidCharacterInputException('Не удалось прочитать изображение.');
        }

        $source = imagecreatefromstring($contents);
        if ($source === false) {
            throw new InvalidCharacterInputException('Файл не является изображением.');
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $side = min($width, $height);

        $canvas = imagecreatetruecolor(self::SIZE, self::SIZE);
        if ($canvas === false) {
            imagedestroy($source);
            throw new InvalidCharacterInputException('Не удалось подготовить изображение.');
        }

        $background = imagecolorallocate($canvas, 255, 255, 255);
        if ($background === false) {
            imagedestroy($source);
            imagedestroy($canvas);
            throw new InvalidCharacterInputException('Не удалось подготовить изображение.');
        }

        imagefilledrectangle($canvas, 0, 0, self::SIZE, self::SIZE, $background);

        imagecopyresampled(
            $canvas,
            $source,
            0,
            0,
            (int) (($width - $side) / 2),
            (int) (($height - $side) / 2),
            self::SIZE,
            self::SIZE,
            $side,
            $side,
        );

        $tmp = tempnam(sys_get_temp_dir(), 'avatar');
        if ($tmp === false || ! imagejpeg($canvas, $tmp, 85)) {
            imagedestroy($source);
            imagedestroy($canvas);
            if (is_string($tmp) && is_file($tmp)) {
                unlink($tmp);
            }
            throw new InvalidCharacterInputException('Не удалось сохранить аватар.');
        }

        $jpeg = file_get_contents($tmp);
        imagedestroy($source);
        imagedestroy($canvas);
        unlink($tmp);

        if ($jpeg === false || $jpeg === '') {
            throw new InvalidCharacterInputException('Не удалось сохранить аватар.');
        }

        return $jpeg;
    }
}
