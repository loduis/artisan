git subsplit init git@github.com:laravel/framework.git
git subsplit publish src/Illuminate/Auth:git@github.com:illuminate/auth.git
git subsplit publish src/Illuminate/Cache:git@github.com:illuminate/cache.git
git subsplit publish src/Illuminate/Config:git@github.com:illuminate/config.git
git subsplit publish src/Illuminate/Console:git@github.com:illuminate/console.git
git subsplit publish src/Illuminate/Container:git@github.com:illuminate/container.git
git subsplit publish --heads="master" src/Illuminate/Contracts:git@github.com:illuminate/contracts.git
git subsplit publish src/Illuminate/Cookie:git@github.com:illuminate/cookie.git
git subsplit publish src/Illuminate/Database:git@github.com:illuminate/database.git
git subsplit publish src/Illuminate/Encryption:git@github.com:illuminate/encryption.git
git subsplit publish src/Illuminate/Events:git@github.com:illuminate/events.git
git subsplit publish src/Illuminate/Exception:git@github.com:illuminate/exception.git
git subsplit publish src/Illuminate/Filesystem:git@github.com:illuminate/filesystem.git
git subsplit publish src/Illuminate/Hashing:git@github.com:illuminate/hashing.git
git subsplit publish --heads="4.1 4.2" src/Illuminate/Html:git@github.com:illuminate/html.git
git subsplit publish src/Illuminate/Http:git@github.com:illuminate/http.git
git subsplit publish src/Illuminate/Log:git@github.com:illuminate/log.git
git subsplit publish src/Illuminate/Mail:git@github.com:illuminate/mail.git
git subsplit publish src/Illuminate/Pagination:git@github.com:illuminate/pagination.git
git subsplit publish src/Illuminate/Queue:git@github.com:illuminate/queue.git
git subsplit publish src/Illuminate/Redis:git@github.com:illuminate/redis.git
git subsplit publish --heads="4.1 4.2" src/Illuminate/Remote:git@github.com:illuminate/remote.git
git subsplit publish src/Illuminate/Routing:git@github.com:illuminate/routing.git
git subsplit publish src/Illuminate/Session:git@github.com:illuminate/session.git
git subsplit publish src/Illuminate/Support:git@github.com:illuminate/support.git
git subsplit publish src/Illuminate/Translation:git@github.com:illuminate/translation.git
git subsplit publish src/Illuminate/Validation:git@github.com:illuminate/validation.git
git subsplit publish src/Illuminate/View:git@github.com:illuminate/view.git
git subsplit publish src/Illuminate/Workbench:git@github.com:illuminate/workbench.git
rm -rf .subsplit/
