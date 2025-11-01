# Facty Pro Editor

🚀 **Advanced AI-Powered Editorial Fact-Checking for WordPress**

A comprehensive fact-checking plugin designed specifically for editors and content teams. Provides deep research verification, SEO analysis, style recommendations, and automated schema markup - all before you hit publish.

---

## ✨ Features

### 📋 **Comprehensive Fact-Checking**
- **Deep Research**: Uses Perplexity AI with real-time web search
- **Claim-by-Claim Analysis**: Verifies each factual claim with sources
- **Confidence Levels**: High, medium, low confidence ratings
- **Issue Types**: Factual errors, outdated info, misleading claims, unverified content
- **Smart Recency**: Prioritizes recent sources for current events
- **Source Attribution**: Every claim linked to credible sources

### 🔍 **SEO Analysis**
- Title optimization (length, power words)
- Meta description analysis
- Content length recommendations  
- Heading structure (H1, H2, H3)
- Image alt text verification
- Internal/external link analysis
- Comprehensive SEO score (0-100)

### ✍️ **Style & Readability**
- Flesch Reading Ease score
- Sentence length analysis
- Passive voice detection
- Adverb usage tracking
- Complex word identification
- Paragraph length recommendations
- Readability score (0-100)

### ⚡ **Background Processing**
- **No Timeouts**: Uses Action Scheduler for reliable processing
- **Progress Tracking**: Real-time progress updates
- **Queue Management**: Handles multiple jobs efficiently
- **Error Recovery**: Automatic retry logic

### 🏷️ **Editorial Workflow**
- **Classic Editor**: Full meta box integration
- **Gutenberg**: Works seamlessly in block editor
- **Verification System**: Editors can mark articles as verified
- **Audit Trail**: Tracks who verified and when
- **Report History**: Keep all analysis reports

### 🌐 **Frontend Features**
- **Verification Badge**: Beautiful, customizable badges
- **ClaimReview Schema**: Google-compliant structured data
- **SEO Benefits**: Enhanced search visibility
- **Trust Signals**: Show readers you care about accuracy

---

## 📦 Installation

### Requirements
- WordPress 6.0+
- PHP 7.4+
- Perplexity AI API key ([Get one here](https://www.perplexity.ai/settings/api))

### Steps

1. **Upload Plugin**
   ```
   wp-content/plugins/facty-pro-editor/
   ```

2. **Activate Plugin**
   - Go to Plugins → Installed Plugins
   - Find "Facty Pro Editor"
   - Click "Activate"

3. **Configure Settings**
   - Go to Settings → Facty Pro Editor
   - Enter your Perplexity API key
   - Configure analysis options
   - Save settings

---

## 🎯 Quick Start

### For Editors

1. **Create or Edit a Post**
   - Open any post or page in WordPress

2. **Find the Facty Pro Meta Box**
   - Scroll to the "Facty Pro: Editorial Fact Checker" box
   - Located below the main editor

3. **Start Fact-Checking**
   - Click "Start Fact Check" button
   - Watch real-time progress updates
   - Wait for completion (30-90 seconds typically)

4. **Review the Report**
   - View comprehensive analysis
   - See fact-check issues with sources
   - Check SEO recommendations
   - Review style suggestions

5. **Fix Issues**
   - Update content based on recommendations
   - Re-run fact check to verify fixes

6. **Verify & Publish**
   - Click "Mark as Verified" (requires publish permissions)
   - Verification badge will appear on frontend
   - ClaimReview schema added automatically

---

## 🛠️ Configuration

### API Settings

**Perplexity API Key** (Required)
- Get from: https://www.perplexity.ai/settings/api
- Supports pay-as-you-go pricing
- Secure storage in WordPress database

**Model Selection**
- **Sonar Pro** (Recommended): Best accuracy
- **Sonar**: Faster, lower cost

**Search Recency Filter**
- Hour: Very recent events
- Day: Breaking news
- **Week** (Recommended): Most topics
- Month: Historical context
- Year: Deep research

### Analysis Features

**Enable SEO Analysis**
- ✅ ON: Full SEO analysis (recommended)
- ❌ OFF: Skip SEO checks

**Enable Style Analysis**
- ✅ ON: Readability and style checks
- ❌ OFF: Facts only

**Enable Readability Analysis**
- ✅ ON: Flesch score and metrics
- ❌ OFF: Skip readability

### Frontend Display

**Show Verification Badge**
- ✅ ON: Display badge on verified articles
- ❌ OFF: No frontend display

**Add ClaimReview Schema**
- ✅ ON: Google-compliant structured data
- ❌ OFF: No schema markup

**Require Verification**
- ✅ ON: Editor must manually verify
- ❌ OFF: Auto-show badge after fact-check

---

## 📊 Understanding Scores

### Fact-Check Score (0-100)

| Score | Status | Meaning |
|-------|--------|---------|
| 95-100 | Verified | Completely accurate, well-sourced |
| 85-94 | Mostly Accurate | Accurate with minor issues |
| 70-84 | Needs Review | Some problems or unverified claims |
| 50-69 | Mixed Accuracy | Significant concerns |
| 30-49 | Multiple Errors | Mostly inaccurate |
| 0-29 | False | Highly misleading |

### SEO Score (0-100)

- **85-100**: Excellent optimization
- **70-84**: Good, minor improvements needed
- **50-69**: Fair, several issues to fix
- **0-49**: Poor, needs significant work

### Readability Score (0-100)

Based on Flesch Reading Ease:
- **90-100**: Very easy (5th grade)
- **80-89**: Easy (6th grade)
- **70-79**: Fairly easy (7th grade)
- **60-69**: Standard (8th-9th grade)
- **50-59**: Fairly difficult (10th-12th grade)
- **30-49**: Difficult (college)
- **0-29**: Very difficult (graduate level)

---

## 🔍 Issue Types Explained

### Factual Error
- **What**: Claim contradicted by credible sources
- **Action**: Update with correct information
- **Sources**: Multiple recent, authoritative references

### Outdated
- **What**: Was true but no longer accurate
- **Action**: Update with current information
- **Note**: Check dates in your sources

### Misleading
- **What**: Technically true but missing context
- **Action**: Add necessary context
- **Example**: Cherry-picked statistics

### Unverified
- **What**: No sources found (doesn't mean false!)
- **Action**: Add sources or remove claim
- **Note**: Not the same as "false"

### Missing Context
- **What**: Needs additional information
- **Action**: Provide full picture
- **Example**: Partial quotes

---

## 💡 Best Practices

### For Editors

1. **Fact-Check Before Publishing**
   - Run analysis on all articles
   - Review every issue raised
   - Fix or justify keeping content

2. **Use Recent Sources**
   - Prefer sources from last week
   - Check publication dates
   - Verify current office holders

3. **Don't Confuse Unverified with False**
   - Unverified = no sources found
   - False = contradicted by evidence
   - Different levels of concern

4. **Re-Check After Major Edits**
   - Content changed? Re-run analysis
   - Scores may change significantly

5. **Leverage SEO Recommendations**
   - Improve titles and descriptions
   - Add alt text to images
   - Structure with headings

### For Site Owners

1. **Set Clear Verification Policies**
   - Who can verify articles?
   - What score threshold for publication?
   - How to handle controversial topics?

2. **Monitor Statistics**
   - Track average scores over time
   - Identify problem areas
   - Celebrate improvements

3. **Train Your Team**
   - Show editors how to use the tool
   - Explain score meanings
   - Share best practices

4. **Optimize API Usage**
   - Choose appropriate recency filter
   - Balance speed vs thoroughness
   - Monitor API costs

---

## 🔧 Technical Details

### Database Tables

**wp_facty_pro_reports**
- Stores all analysis reports
- Includes scores and full JSON data
- Verification status tracking

### Post Meta Fields

- `_facty_pro_verified`: Boolean
- `_facty_pro_verified_at`: Datetime
- `_facty_pro_verified_by`: User ID
- `_facty_pro_last_report`: Report ID
- `_facty_pro_last_check`: Datetime
- `_facty_pro_fact_score`: Integer (0-100)

### Background Jobs

Uses **Action Scheduler** for:
- Fact-checking analysis
- API rate limiting
- Queue management
- Error recovery

### API Calls

**Per Fact-Check**:
- 1 Perplexity API call (fact-checking)
- No other external APIs

**Rate Limits**:
- Respects Perplexity's rate limits
- Queue system prevents overload

---

## 📚 Resources

### Documentation
- [Perplexity AI Docs](https://docs.perplexity.ai/)
- [Google ClaimReview Guidelines](https://developers.google.com/search/docs/appearance/structured-data/factcheck)
- [Action Scheduler Docs](https://actionscheduler.org/)

### Getting API Keys
- [Perplexity AI Settings](https://www.perplexity.ai/settings/api)

### Support
- Plugin Documentation: See this README
- WordPress Support Forums
- GitHub Issues (if applicable)

---

## ⚠️ Important Notes

### What This Plugin Does:
✅ Fact-checks content using AI and web search  
✅ Provides SEO and style analysis  
✅ Adds verification badges and schema  
✅ Helps editors improve content  

### What This Plugin Doesn't Do:
❌ Replace human editorial judgment  
❌ Guarantee 100% accuracy  
❌ Auto-correct your content  
❌ Make final publication decisions  

**Remember**: This is a tool to *assist* editors, not replace them. Always apply human judgment to fact-check results.

---

## 🔐 Security

- API keys stored securely in database
- Nonce verification on all AJAX calls
- Capability checks for sensitive actions
- Sanitized inputs and outputs
- No data sent to third parties (except Perplexity)

---

## 🎨 Customization

### Frontend Badge CSS

The badge can be customized via CSS:

```css
.facty-pro-badge {
    /* Customize badge appearance */
}

.facty-pro-badge-excellent {
    /* Green badge (score 85+) */
}

.facty-pro-badge-good {
    /* Blue badge (score 70-84) */
}

.facty-pro-badge-fair {
    /* Yellow badge (score 50-69) */
}

.facty-pro-badge-poor {
    /* Red badge (score <50) */
}
```

---

## 📝 Changelog

### Version 1.0.0
- Initial release
- Perplexity AI integration
- SEO analysis
- Style analysis
- Background processing
- Verification system
- ClaimReview schema
- Classic Editor support
- Gutenberg support

---

## 📄 License

GPL v2 or later

---

## 🤝 Credits

**Developed by**: Mohamed Sawah  
**Website**: https://sawahsolutions.com  
**Powered by**: Perplexity AI, Action Scheduler

---

## 🚀 Roadmap

### Planned Features
- [ ] Bulk fact-checking
- [ ] Custom scoring thresholds
- [ ] Email notifications
- [ ] Scheduled re-checks
- [ ] Team collaboration features
- [ ] Advanced reporting dashboard
- [ ] API access
- [ ] Multi-language support

---

## ❓ FAQ

**Q: How much does it cost?**  
A: The plugin is free, but requires a Perplexity AI API key (pay-as-you-go).

**Q: How long does fact-checking take?**  
A: Typically 30-90 seconds, depending on article length and complexity.

**Q: Can I fact-check old posts?**  
A: Yes! Works on any post or page, regardless of publish date.

**Q: Will it slow down my site?**  
A: No. All processing happens in the background using Action Scheduler.

**Q: What if the AI makes a mistake?**  
A: That's why human review is required! Editors must verify before publication.

**Q: Does it work with custom post types?**  
A: Currently supports posts and pages. Custom post types coming soon.

**Q: Can I disable certain features?**  
A: Yes. All analysis features can be toggled in settings.

**Q: Is my API key secure?**  
A: Yes. Stored encrypted in your WordPress database.

**Q: How do I get support?**  
A: Check this README first, then reach out via support channels.

**Q: Can multiple editors use it?**  
A: Yes! Multiple editors can use it simultaneously.

---

**Built with ❤️ for fact-based journalism**
