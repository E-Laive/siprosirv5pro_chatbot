"""Representative smoke tests for optional skill scripts; run with unittest discover."""

import json
import sys
import tempfile
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT / "scripts"))
from generate_web_theme import TokenError, render  # noqa: E402
from audit_style_literals import findings  # noqa: E402


class ThemeExportTests(unittest.TestCase):
    def setUp(self):
        self.tokens = json.loads((ROOT / "assets/theme-token-examples/tokens.example.json").read_text(encoding="utf-8"))

    def test_theme_modes_typography_and_semantics(self):
        css = render(self.tokens)
        self.assertIn('[data-theme="light"]', css)
        self.assertIn('[data-theme="dark"]', css)
        self.assertIn('[data-theme="system"]', css)
        self.assertIn('prefers-color-scheme: dark', css)
        self.assertIn('--color-primary:', css)
        self.assertIn('--font-family-ui:', css)
        self.assertIn('--space-4:', css)
        self.assertIn('color-scheme: dark;', css)

    def test_mismatched_theme_keys_rejected(self):
        del self.tokens["themes"]["dark"]["color"]["danger"]
        with self.assertRaises(TokenError):
            render(self.tokens)

    def test_invalid_css_value_rejected(self):
        self.tokens["typography"]["family"]["ui"] = "evil; } body { color: red"
        with self.assertRaises(TokenError):
            render(self.tokens)


class StyleAuditTests(unittest.TestCase):
    def test_read_only_and_exclusions(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            (root / "app.css").write_text(".x { color: #123456; background: rgb(0,0,0); }", encoding="utf-8")
            (root / "node_modules").mkdir()
            (root / "node_modules" / "test.css").write_text(".z { color: #aabbcc; }", encoding="utf-8")
            found = findings(root)
            self.assertEqual(len(found), 1)
            self.assertEqual(str(found[0][0]), "app.css")
            self.assertTrue((root / "app.css").exists())


if __name__ == "__main__":
    unittest.main()
