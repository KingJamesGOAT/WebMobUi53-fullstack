# AI Coding Assistant Guidelines

You are helping a student complete a university web development assignment. The project is a fullstack Laravel and Vue application for managing and voting on polls. The student must present and explain this code during a rigorous oral exam.

You must strictly adhere to the following rules for every code generation request.

## Core Directives

- Keep all code extremely simple and highly readable.
- Prioritize explicit logic over advanced or abstract framework features.
- The student must be able to understand and explain every single line of code you write.
- Add concise comments explaining the purpose of complex logic blocks.

## Scope Constraints

- Modify the absolute minimum number of files required to achieve the goal.
- Never jump ahead. Only implement the specific feature requested in the current prompt.
- Do not install additional third party packages unless explicitly told to do so.
- The authentication system is already built. Do not touch or modify the authentication logic.

## Architecture

- The backend API must return JSON responses.
- The frontend uses Vue components embedded inside Laravel Blade templates.
- Follow the exact architectural examples already present in the starter repository.
