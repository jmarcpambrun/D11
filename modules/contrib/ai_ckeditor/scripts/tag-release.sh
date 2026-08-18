#!/bin/sh

# Repository to build the release from. Adjust if the project's machine name or
# hosting differs.
REPO="git@git.drupal.org:project/ai_ckeditor.git"

# Color definitions.
GREEN="$(printf '\033[0;32m')"
WHITE_ON_BLUE="$(printf '\033[104;37m')"
NC="$(printf '\033[0m')"

# Checks whether or not a dependency is found.
# @param $1 string The command to check (node).
# @param $2 string The version constraint as regex (v2[4-9].*).
# @param $3 string An error message that's presented on failure.
check_dependency() {
  CMD="$1"
  REQ="$2"
  MSG="$3"
  VERSION="$($CMD --version 2>/dev/null)"
  case "$VERSION" in
    $REQ) ;;
    *)
      printf "%s Found: %s\n" "$MSG" "$VERSION"
      exit 1
      ;;
  esac
}

check_dependency "node" "v2[4-9].*" "This script requires NodeJS >= v24."
check_dependency "npm" "1[1-9].*" "This script requires npm >= v11."

# Create temp directory (macOS + Linux compatible).
WORK_DIR="$(mktemp -d 2>/dev/null || mktemp -d -t ai_ckeditor_release)"

printf "Which branch to use: "
read BRANCH

printf "Tag to create release for: "
read TAG

case "$TAG" in
  0.*)
    ;;
  *)
    printf "Are you sure you want to use %s? Type it again to confirm: " "$TAG"
    read RETAG
    if [ "$RETAG" != "$TAG" ]; then
      printf "Aborted.\n"
      exit 2
    fi
    ;;
esac

printf "%s[0/5] Opening working directory %s …%s\n" "$WHITE_ON_BLUE" "$WORK_DIR" "$NC"

# Cross-platform open.
if command -v open >/dev/null 2>&1; then
  open "$WORK_DIR"
elif command -v xdg-open >/dev/null 2>&1; then
  xdg-open "$WORK_DIR" >/dev/null 2>&1 &
fi

printf "%s[1/5] Cloning AI CKEditor into working directory …%s\n" "$WHITE_ON_BLUE" "$NC"

cd "$WORK_DIR" || exit 1
git clone -q "$REPO"
cd ai_ckeditor || exit 1
git checkout "$BRANCH" || exit 1

printf "%s[2/5] Building UI …%s\n" "$WHITE_ON_BLUE" "$NC"

# The module lives at the repository root, so the build runs against the
# current directory.
npm version "$TAG" --allow-same-version --no-git-tag-version || exit 1
npm install || exit 1
npm run build || exit 1

printf "%s[3/5] Committing built UI …%s\n" "$WHITE_ON_BLUE" "$NC"

git add -f \
  package.json \
  package-lock.json \
  js/build

git commit -q -m "AI CKEditor $TAG"
git tag -a "$TAG"

printf "  %s%s tag created locally.%s\n" "$GREEN" "$TAG" "$NC"

printf "%s[4/5] Removing built UI …%s\n" "$WHITE_ON_BLUE" "$NC"

npm version "0.0.0" --allow-same-version --no-git-tag-version || exit 1

git add -f \
  package.json \
  package-lock.json

git rm -rf \
  js/build

git commit -q -m "Back to dev."

printf "  %sBuilt UI removed locally.%s\n" "$GREEN" "$NC"

printf "%s[5/5] Please verify the 2 new commits and tag at %s/ai_ckeditor …%s\n" \
  "$WHITE_ON_BLUE" "$WORK_DIR" "$NC"

printf "Are you sure you want to push these 2 commits and tag? <y/N> "
read PROMPT

if [ "$PROMPT" = "y" ]; then
  git push -q || exit 1
  git push -q --tags || exit 1
  printf "  %s%s tag pushed to drupal.org.%s\n" "$GREEN" "$TAG" "$NC"
else
  printf "Aborted.\n"
  exit 0
fi
