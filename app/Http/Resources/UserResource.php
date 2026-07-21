<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'avatar_id' => $this->avatar_id,
            'avatar_url' => $this->avatar?->url,
            'phone' => $this->phone,
            'gender' => $this->gender,
            'student_id' => $this->student_id,
            'class_name' => $this->class_name,
            'generation' => $this->generation,
            'province' => $this->province,
            'trainer_id' => $this->trainer_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
